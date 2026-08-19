---
name: oro-behat-debugging
description: "Use when a Behat scenario in Oro Commerce 6.1 fails, hangs or flakes — element not found, intermittent runs, AJAX races, waitForAjax with fetch/XHR, step discovery (`-dl`/`-di`), snippets, verbosity, ScreenshotTrait, `And I wait for action` blocking CI, var/log forensics for hidden 500s, Xdebug across split CLI + PHP-FPM. Also without the word \"behat\": step definitions, feature files, Mink, Gherkin."
---

# Debugging Oro Commerce v6.1 Behat Tests

## Debugging Flow

Cheapest signal first; most failures resolve at steps 1-3.

1. **Read the failure output and `var/log/<env>.log`** — "element not found" is often a 500. The cause is in the server log, not the Behat trace.
2. **Re-run with `-v` / `-vv` / `-vvv`** — matched step definition, then hook execution, then the full matcher trace. One level at a time.
3. **Capture state** — `And I take screenshot` (`ScreenshotTrait` captures the cursor, unless an alert shows); `dump($variable)` inside a Context.
   - **Several plausible gates? One diagnostic step, not N runs** — a `@Then I dump X for :arg` that queries every candidate and throws the combined state: `references/breadth-first-diagnostics.md`.
4. **Isolate the scenario** — a failing 3-step scenario tells you far more than a failing 30-step one.
5. **`--stop-on-failure`** — tight loop on a single failing scenario.
6. **Intermittent is an AJAX race, not a flake** — `references/ajax-flake.md`. `And I wait` is not a fix.
7. **Wrong logic inside a Context: attach Xdebug** — below.

## Xdebug Split-Process Debugging

The part the Oro docs gloss over. Runner and application are **two PHP processes**: `bin/behat` holds Contexts, steps, elements, fixtures; PHP-FPM holds controllers, services, listeners. Xdebug on one misses everything on the other — two targets, two ports.

CLI attaches via `XDEBUG_MODE=debug XDEBUG_SESSION=1 php bin/behat …`; FPM only when the browser session carries the `XDEBUG_SESSION` cookie, set from a `@BeforeScenario` hook. Snippets, path mapping, silent failures: `references/xdebug-split.md`.

## Step Discovery

Don't guess step wording; list it, and generate skeletons rather than writing them:

```bash
php bin/behat -dl -s OroUserBundle                 # names only
php bin/behat -di -s OroUserBundle                 # names + descriptions + examples
php bin/behat path/to/your.feature --dry-run --append-snippets --snippets-type=regex
```

Tag filtering and the verbosity progression: `references/step-discovery.md`.

## Key Pitfalls

1. **`waitForAjax` on non-jQuery requests.** It tracks only jQuery-registered XHR, so native `fetch()` and raw `XMLHttpRequest` never enter its queue and it returns while the request is in flight. Wait for an observable DOM state instead.
2. **Committing `And I wait for action`.** It blocks on stdin until you press return — fine locally, but CI hangs until timeout. A pre-commit grep pays for itself.
3. **Path mapping misconfigured in split-process Xdebug.** When container paths differ from the IDE's, breakpoints silently do not fire — no error, no warning. Verify with a trivial breakpoint on a known-loaded file first.

## See Also

In `references/`: `xdebug-split.md` · `step-discovery.md` · `ajax-flake.md` · `breadth-first-diagnostics.md` · `performance-tmpfs.md` (PostgreSQL in tmpfs) · `v6.1.md` · `v7.0.md`

Writing tests: **oro-behat-testing**. Remote/staging: **oro-e2e-testing**. Upstream: [Oro Debug Behat Tests](https://doc.oroinc.com/backend/automated-tests/debug-behat-tests/)
