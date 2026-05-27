# Chrome Headless for Oro Behat in CI

Oro's `@javascript` steps drive a real Chrome instance via ChromeDriver + Mink. In CI you need headless Chrome with a specific flag set — anything else causes vague JS errors, missing UI hooks, or phantom click failures.

## Mandatory Flags

```
--headless
--no-sandbox
--disable-dev-shm-usage
--disable-extensions
--no-pings
--window-size=1920,1080
--load-extension=vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension
```

Per-flag rationale:

| Flag | Why |
|------|-----|
| `--headless` | No display in CI. Use `--headless=new` on Chrome 112+ for closer parity with headed mode. |
| `--no-sandbox` | Docker containers run as root. Chrome's sandbox needs namespace isolation it can't get inside a container without extra privileges. Without this, Chrome exits immediately with "Running as root without --no-sandbox is not supported". |
| `--disable-dev-shm-usage` | `/dev/shm` in containers defaults to 64MB. Chrome uses it for tab process shared memory and crashes with "session deleted because of page crash" once you load any non-trivial page. This flag redirects to `/tmp`. Alternative: `docker run --shm-size=2g`. |
| `--disable-extensions` AND `--load-extension=...` | **Paradox that matters.** `--disable-extensions` disables everything except extensions loaded via `--load-extension`. Oro needs its custom extension loaded, but no others (no ad blockers, no password managers, nothing that would interfere with test hooks). Both flags together: only Oro's extension runs. |
| `--no-pings` | Disables hyperlink auditing pings that can cause Mink step timeouts. |
| `--window-size=1920,1080` | Forces responsive breakpoints to desktop mode. Without this, headless Chrome defaults to 800x600 and Oro's mobile layout activates — tests written against the desktop layout silently fail. |

## ChromeDriver Version Pinning

Pin the full patch version, e.g., `selenium/standalone-chrome:142.0.7444.175`. **Never use `:latest`.**

Reason: Chrome auto-updates on the Selenium image's base layer. ChromeDriver tracks Chrome, but there's a lag window (hours to days) where Chrome updates and ChromeDriver hasn't caught up. During that window all tests fail with `SessionNotCreatedException: session not created: This version of ChromeDriver only supports Chrome version N`.

Pin, bump deliberately, test the bump in a branch before merging to the shared compose file.

## PHP `memory_limit = -1`

Non-optional in Oro's test image. Behat contexts hold DoctrineFixtures, parsed YAML graph data, and accumulated scenario state — 512MB is not enough for the full suite. The canonical test Dockerfile sets:

```dockerfile
RUN echo 'memory_limit = -1' > /usr/local/etc/php/conf.d/behat-memory.ini
```

If you see `PHP Fatal error: Allowed memory size of X bytes exhausted` mid-scenario, your test image is missing this line.

## Extension Mount Path

The extension lives in the vendor tree: `vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension`. It's shipped as source (unpacked, not a `.crx`), which is why `--load-extension=<path>` (not `--load-unpacked=<path>` — that's a different API) works.

In Docker: the path must be visible **inside the `chrome` container**. The common setup mounts it read-only from the PHP container's vendor directory:

```yaml
chrome:
  image: selenium/standalone-chrome:142.0.7444.175
  volumes:
    - ./vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension:/chrome-extension:ro
```

And the behat mink config references `/chrome-extension` (the in-container path), not the host path.
