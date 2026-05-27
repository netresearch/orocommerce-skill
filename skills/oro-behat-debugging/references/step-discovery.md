# Step Discovery, Verbosity, and Tag Filtering

The fastest way to write or debug a scenario is to find out what steps already exist and what Behat is actually doing when it runs them.

## Listing Steps

`-dl` (definitions: list) prints matched regex patterns only. `-di` (definitions: info) adds descriptions and examples from the `@Given`/`@When`/`@Then` docblocks, which tell you what the step expects and how it behaves.

```bash
php bin/behat -dl -s OroUserBundle                  # pattern list
php bin/behat -di -s OroUserBundle                  # patterns + docblocks + examples
php bin/behat -dl                                   # all suites (noisy)
```

Filter with grep — the lists are long:

```bash
php bin/behat -dl -s OroUserBundle | grep -i "flash message"
php bin/behat -dl -s OroCustomerBundle | grep -i "login"
php bin/behat -dl -s OroProductBundle | grep -i "grid"
```

The `-s` flag restricts to a single suite. Suite names come from `behat.yml` (or project-specific `behat-*.yml`). Without `-s`, Behat lists every registered suite's steps, which is useful when you don't know which bundle owns a step.

## Generating Missing Step Skeletons

When a feature file references steps that don't exist yet, Behat normally errors out with "undefined step". Let it write the skeletons instead:

```bash
php bin/behat path/to/your.feature --dry-run --append-snippets --snippets-type=regex
```

- `--dry-run` parses steps without executing them (and without booting the browser).
- `--append-snippets` writes stubs for undefined steps into the Context class of the matching suite.
- `--snippets-type=regex` uses regex patterns rather than turnip. Oro conventions use regex throughout — don't mix the two.

Review the generated Context before committing. The stubs have a `throw new PendingException()` body you need to replace.

## Verbosity Progression

Escalate one level at a time — more verbose output drowns the signal if you jump straight to `-vvv`.

| Flag | What it adds |
|------|--------------|
| (none) | Pass/fail lines, failure stack trace |
| `-v` | Matched step definition path for each step — "which PHP method ran for this step?" |
| `-vv` | `+` hook execution (`@BeforeScenario`, `@AfterStep`, isolator calls) — tells you which hook is slow or throwing |
| `-vvv` | `+` full step matcher trace, regex candidates considered, priority resolution — needed when the wrong step definition wins an overlap |

Pairing `-vv` with `--stop-on-failure` is the highest-signal combination when chasing one specific failure: you see every hook that ran before the failure plus the exact step method that blew up.

## Tag Filtering

Behat's tag expression language:

```bash
php bin/behat --tags=@smoke                   # only @smoke scenarios
php bin/behat --tags="@smoke&&@checkout"      # both tags
php bin/behat --tags="@smoke,@checkout"       # either tag (comma = OR)
php bin/behat --tags="~@wip"                  # exclude @wip
php bin/behat --tags="@smoke&&~@flaky"        # @smoke except @flaky
```

Tags live on scenarios or whole features. `@wip` is a convention for scenarios in progress — excluding it from CI keeps half-written work out of the build. Use `@smoke` for critical-path scenarios you want to run first or standalone.

## Dry-Run Without Snippet Generation

`--dry-run` alone is useful for validating feature files — it parses them and resolves step references without booting the browser or touching the DB. Fast sanity check before committing a new feature.

```bash
php bin/behat path/to/new.feature --dry-run
```

Pair with `-v` to see which step definition each line resolves to without actually running anything.
