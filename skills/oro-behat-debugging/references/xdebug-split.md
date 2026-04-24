# Xdebug Split-Process Debugging

The Behat runner and the application under test are two PHP processes. To step through both test code and application code in one run you need two debug targets. This reference walks through the setup.

## The Two Processes

| Process | What runs there | Typical location | Breakpoints |
|---------|-----------------|------------------|-------------|
| CLI runner | `bin/behat`, Contexts, step definitions, Elements, fixtures | `toolbox` container | Test-side PHP |
| PHP-FPM | Controllers, services, event listeners, repositories | `app` or `php-fpm` container | Application PHP |

Mink drives a browser (Chromedriver/Selenium) that sends HTTP to FPM. The CLI runner never sees those requests — it only sees the assertions you write after them.

## Enabling Xdebug on Each Process

### CLI runner

Enable Xdebug only for the invocation you care about:

```bash
XDEBUG_MODE=debug XDEBUG_SESSION=1 XDEBUG_CONFIG="client_host=host.docker.internal client_port=9003" \
    php bin/behat path/to/feature.feature
```

`XDEBUG_SESSION=1` is the trigger Xdebug 3 uses when `start_with_request=trigger`. Keeping Xdebug off for normal test runs avoids the per-step performance tax.

### PHP-FPM

Xdebug needs to already be loaded inside the FPM container, with `start_with_request=trigger`. The activation trigger is the `XDEBUG_SESSION` cookie on the incoming request. Set it from a Context step so every Mink request inside the scenario carries it:

```php
/**
 * @BeforeScenario @debug
 */
public function enableXdebugForScenario(): void
{
    $this->getSession()->setCookie('XDEBUG_SESSION', 'PHPSTORM');
}
```

Tag scenarios `@debug` to opt in. Without tagging, every scenario triggers Xdebug on FPM, which slows the whole suite.

## IDE Listener Configuration

Two simultaneous debug targets = two listeners, each on a distinct port:

- **Port 9003** — default. Point the CLI runner at it.
- **Port 9004** (or any free port) — point FPM at it via `xdebug.client_port=9004` in the container's Xdebug config.

In PHPStorm: open **Run > Edit Configurations > PHP Remote Debug** twice, one per port, both with the same IDE key (e.g. `PHPSTORM`) so either side can attach. Start both listeners before running Behat.

Alternative: same port for both, but then you can only pause in one process at a time — the second connection waits. Fine for most debugging, limiting when a Context and a controller interact in one request.

## Path Mapping

The container sees code at (e.g.) `/var/www/html/src/...` while the IDE sees `/home/you/project/src/...`. Xdebug sends file paths from the container; the IDE has to translate them to local paths before resolving breakpoints. Per-target mapping:

- CLI runner container: map `/var/www/html` → `$PROJECT_DIR$`
- FPM container: map `/var/www/html` → `$PROJECT_DIR$`

Paths can differ between containers (bind mounts, overlayfs) — verify `realpath` inside each container matches what the IDE configured.

## Silent-Failure Checklist

When nothing fires:

1. **Is Xdebug actually loaded in that process?** `php -v` inside the container, check for the Xdebug line. FPM may need `php-fpm -i` (or the admin status page) instead — CLI and FPM load different INI files.
2. **Is `xdebug.mode` set to `debug`?** `develop` alone is not enough.
3. **Is the trigger present?** CLI: `XDEBUG_SESSION=1` env var. FPM: `XDEBUG_SESSION` cookie on the request (inspect in browser devtools or via `dump($_COOKIE)`).
4. **Is the client host reachable?** From inside a Linux container, `host.docker.internal` requires `extra_hosts: ["host.docker.internal:host-gateway"]` in compose. Test with `nc -zv host.docker.internal 9003`.
5. **Is path mapping right?** Set a breakpoint on the first line of a file you know is hit (e.g. `bin/behat` or a controller's `__invoke`). If that doesn't trigger, the mapping is wrong.
6. **Is another IDE session holding the port?** Xdebug only attaches once per port; a stale PHPStorm instance will steal every connection.

## Cookie Propagation Gotcha

`setCookie` on Mink writes to the browser session, which means the cookie is gone after the browser is reset between scenarios. Setting it in `@BeforeScenario` rather than `@BeforeSuite` is deliberate — a suite-level hook runs once and the cookie disappears as soon as the first scenario ends.

For ad-hoc debugging without modifying Context code, add a one-shot Gherkin step you delete before commit:

```gherkin
Given I set cookie "XDEBUG_SESSION" to "PHPSTORM"
```

Most Oro projects already ship a step like this via `OroTestFrameworkBundle` — check `php bin/behat -dl | grep -i cookie`.
