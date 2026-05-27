---
name: oro-behat-debugging
description: "Use when debugging or troubleshooting Behat tests in Oro Commerce 6.1 — failing scenarios, intermittent/flaky runs (passes 4 of 5, fails 3 of 20), missing elements, AJAX races, waitForAjax limits with fetch/XHR, step definition discovery and grepping `-dl`/`-di` output, snippet generation, verbosity flags `-v`/`-vv`/`-vvv` and quieting passing-step noise, screenshots, ScreenshotTrait, breadth-first multi-gate dumps, scenario isolation, --stop-on-failure, step ordering or missing Background, fixture-vs-render-timing differentiation, consumer/queue state mid-scenario, leftover `And I wait for action` blocking CI and pre-commit detection, tmpfs PostgreSQL tuning, PgsqlIsolator, var/log forensics for hidden 500s, and Xdebug across split CLI + PHP-FPM (two listeners, XDEBUG_SESSION cookie, path mapping). Relevant whenever a Behat scenario fails, hangs, flakes, or needs investigation — even when phrased casually without \"behat\" but mentioning step definitions, feature files, Mink, Gherkin, or ScreenshotTrait."
---

# Debugging Oro Commerce v6.1 Behat Tests

## Debugging Flow

Start with the cheapest signal and escalate. Most failures resolve at steps 1-3.

1. **Read the failure output and `var/log/<env>.log`** — Behat surfaces "element not found" for what is often a 500 in the app. The real cause lives in the server log, not the Behat stack trace.
2. **Re-run with `-v` / `-vv` / `-vvv`** — verbose shows the matched step definition, very verbose adds hook execution, debug adds the full step matcher trace. Escalate one level at a time.
3. **Capture state** — insert `And I take screenshot` at the suspected failure point. Oro's `ScreenshotTrait::takeScreenshot` captures cursor position too, except when a browser alert is showing. Drop `dump($variable)` into Context classes to inspect runtime state.
   - **Multi-gate failure? Go breadth-first with ONE diagnostic step, not N sequential runs.** When a scenario fails with an opaque "thing missing / empty" symptom and there are several plausible gates (visibility tables + scope + Elasticsearch index + system config + feature flag, etc.), write a single `@Then I dump X for :arg` step in the Context that queries EVERY plausible culprit in one shot and throws the combined state as a `RuntimeException`. One 2–4 minute behat cycle then returns all the evidence at once. Serialised hypothesis-at-a-time runs compound cost and context-switching. Wrap each sub-query in try/catch so a missing table or wrong service name doesn't suppress the rest. Revert the diagnostic step before committing — it's scaffolding, not permanent test coverage. See the `Comprehensive Visibility/State Dump` pattern in `references/breadth-first-diagnostics.md`.
4. **Isolate the scenario** — strip every step not strictly required to reproduce. A failing 3-step scenario tells you far more than a failing 30-step one.
5. **Run with `--stop-on-failure`** — tight feedback loop when chasing a single failing scenario.
6. **If intermittent: treat it as an AJAX race, not a flake** — see `references/ajax-flake.md`. Adding `And I wait` is not a fix.
7. **If logic inside a Context is wrong: attach Xdebug** — see Xdebug Split-Process below.

## Xdebug Split-Process Debugging

This is the part the Oro docs gloss over and it is the highest-leverage technique in the skill.

The Behat runner and the application under test are **two separate PHP processes**, usually in two containers:

- **CLI runner** — `bin/behat` itself. Runs Context classes, step definitions, element classes, fixtures. Breakpoints in PHP test code fire here.
- **PHP-FPM** — serves the Mink browser's HTTP requests. Breakpoints in controllers, services, repositories, event listeners fire here.

Xdebug on only one of them misses everything on the other. Debugging both requires two targets listening on two ports.

Minimal CLI attach:

```bash
XDEBUG_MODE=debug XDEBUG_SESSION=1 php bin/behat path/to/feature.feature
```

Debugging FPM requires setting the `XDEBUG_SESSION` cookie inside the Mink browser session so FPM activates its debugger — Mink's Selenium2 driver supports this directly:

```php
$this->getSession()->setCookie('XDEBUG_SESSION', 'IDEKEY');
```

Put that in a `@BeforeScenario` hook or a dedicated debug step. Full setup (two listeners, path mapping, container networking, common silent failures) in `references/xdebug-split.md`.

## Step Discovery & Verbosity

Don't guess step wording. List what's actually available:

```bash
php bin/behat -dl -s OroUserBundle                 # names only
php bin/behat -di -s OroUserBundle                 # names + descriptions + examples
php bin/behat -dl -s OroUserBundle | grep "grid"   # filter
```

Generate skeletons for undefined steps instead of writing them by hand:

```bash
php bin/behat path/to/your.feature --dry-run --append-snippets --snippets-type=regex
```

Tag filtering for focused runs:

```bash
php bin/behat --tags=@smoke
php bin/behat --tags="@smoke&&@checkout"    # AND
php bin/behat --tags="@smoke,@checkout"     # OR
php bin/behat --tags="~@wip"                # NOT
```

Full reference: `references/step-discovery.md`.

## Pausing for Manual Inspection

```gherkin
And I wait for action
```

Prints "Press [RETURN] to continue..." and blocks until you hit return. Local only — in CI this hangs the job until it times out. Add a pre-commit check (grep for the phrase) if this bites repeatedly.

## Key Pitfalls

1. **Debugging the wrong process.** Xdebug on only the CLI runner never hits controller breakpoints; on only FPM it never hits Context breakpoints. Two targets, two ports, two breakpoint sets.
2. **`waitForAjax` on non-jQuery requests.** The helper only tracks jQuery-registered XHR. Native `fetch()` and raw `XMLHttpRequest` never enter its queue, so it returns immediately while the request is still in flight. Wait for an observable DOM state, not the AJAX queue.
3. **Committing `And I wait for action`.** CI blocks forever on the stdin read. Budget a pre-commit hook for it.
4. **Not reading `var/log/<env>.log` after a failure.** A server-side exception in a controller surfaces as a frontend "element not found" — Behat can't see the 500, only the missing element that would have rendered on success.
5. **Path-mapping misconfigured in split-process Xdebug.** When container paths differ from the IDE's local paths, breakpoints silently don't fire. No error, no warning — the debugger just skips them. Always verify by setting a trivial breakpoint on line 1 of a known-loaded file first.

## See Also

- `references/xdebug-split.md` — Full two-process Xdebug setup, cookie propagation, path mapping, silent-failure checklist
- `references/step-discovery.md` — `-dl`/`-di`, snippet generation, tag filtering, verbosity progression
- `references/ajax-flake.md` — Race conditions, detached element references, backend-vs-HTTP completion, spin predicate pattern
- `references/performance-tmpfs.md` — PostgreSQL in tmpfs for faster test runs (advanced, not in official docs)
- `references/v6.1.md` — 6.1-stable debugging specifics
- `references/v7.0.md` — 7.x notes (placeholder)
- For writing tests: **oro-behat-testing** skill
- For remote/staging runs: **oro-e2e-testing** skill
- Upstream: [Oro Debug Behat Tests](https://doc.oroinc.com/backend/automated-tests/debug-behat-tests/)
