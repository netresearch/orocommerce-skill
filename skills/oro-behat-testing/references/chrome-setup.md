# Chrome and ChromeDriver Setup for Behat

Oro's Behat tests drive a real Chrome instance via Mink + ChromeDriver. A handful of settings are non-negotiable for Docker and headless runs — skipping them causes flaky "element not found" errors that look like test bugs but are actually browser misconfigurations.

## ChromeDriver Installation

ChromeDriver must match the installed Chrome major version. In 6.1 the common pattern was "install latest"; in 7.1-dev Oro's own CI pins a specific version (`CHROME_DRIVER_VERSION=142.0.7444.175`) to avoid surprise breakage. On a dev box:

```bash
# macOS
brew install --cask chromedriver

# Linux (Debian/Ubuntu)
apt-get install chromium-driver

# Verify
chromedriver --version
```

## Starting ChromeDriver

ChromeDriver runs as a long-lived process on port 9515 (Behat connects over HTTP):

```bash
chromedriver --port=9515 --whitelisted-ips='' --url-base=/wd/hub
```

Point Mink at it in `behat.yml.dist`:

```yaml
default:
  extensions:
    Behat\MinkExtension:
      base_url: 'http://dev.oro.local'
      browser_name: chrome
      chrome:
        api_url: 'http://localhost:9515'
```

## Mandatory Headless Flags

Oro's own Jenkins pipeline passes these flags to every headless Chrome run. All of them are load-bearing for Docker:

```
--headless
--no-sandbox
--disable-dev-shm-usage
--disable-extensions
--no-pings
--window-size=1920,1080
--load-extension=vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension
```

- `--no-sandbox` is required inside a Docker container running as root; without it Chrome refuses to launch.
- `--disable-dev-shm-usage` points temp storage at `/tmp` instead of `/dev/shm` — Docker's default 64MB `/dev/shm` is too small for Chrome and crashes silently under load.
- `--window-size=1920,1080` — Oro's default layouts assume a desktop viewport. Narrower windows trigger the mobile menu and break selectors that target desktop-only elements.
- `--disable-extensions` disables user extensions but does NOT disable `--load-extension`, which loads Oro's own.

## Oro's Custom Chrome Extension

Oro ships a Chrome extension at `vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension`. It injects hooks that let Behat see in-flight AJAX requests and page readiness state — without it, `waitForAjax()` has nothing to wait on and tests race against unloaded DOM.

In Docker: mount the `vendor/` tree into any separate Chrome/selenium container, or run Chrome in the same container as PHP so the path resolves. Vanilla `selenium/standalone-chrome` images break without the mount.

## PHP `memory_limit = -1`

Oro's test Docker image sets PHP `memory_limit = -1` (unlimited). This is non-optional — Behat + the test kernel + Alice fixture loading routinely cross 512MB on realistic feature runs. Capped memory causes scenarios to die mid-fixture-load with confusing OOM traces. Mirror it in `php.ini` for any test container you build yourself.
