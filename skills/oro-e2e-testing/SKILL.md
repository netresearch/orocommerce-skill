---
name: oro-e2e-testing
description: "Use for any Behat task targeting a deployed Oro Commerce 6.1 application (staging, QA, prod-clone, prod) rather than a local dev/CI stack. Covers running, configuring, and troubleshooting end-to-end Behat: --skip-isolators and --skip-isolators-but-load-fixtures, ORO_DB_DSN placement (.app-env.local vs .env-app.test.local), aligning local source/migrations to the deployed tag, and the oro/e2e-tests vendor package. Use for .behat-secrets.yml authoring (login.* keys, <Secret:> syntax, .dist template, .gitignore, remediation when secrets leak into git history). Use for ChromeDriver/remote-browser plumbing: port/url-base flags, Mink 404s, selenium grid endpoints, credential rotation. Use for watch mode (pause-on-failure, retry-from-line prompt), Reload Page Healer, OpenAI Healer, custom HealerInterface classes plus oro_test.behat.healer tag, disambiguating HealerInterface as Oro-Behat-specific. Skip for local-only Behat, PHPUnit functional tests, k6 load, or CI against ephemeral containers."
---

# OroCommerce v6.1 End-to-End Behat Testing

## Overview

E2e tests drive a real browser against a deployed Oro application. Unlike functional/integration Behat (local, service-isolated, DB rolled back per scenario), e2e tests **disable all isolators** and interact with the app exclusively through Mink + ChromeDriver. The target can be staging, a dedicated QA instance, or a throwaway production clone.

The load-bearing distinction is the isolation flag:

- `--skip-isolators` — **pure e2e.** Disables DB, cache, and service-container isolation entirely. Tests only talk to the browser. No fixture loading. No direct service calls.
- `--skip-isolators-but-load-fixtures` — **hybrid.** Keeps the fixture-loader isolator active, disables everything else. Requires `ORO_DB_DSN` pointing at the remote DB **and** local source code whose migrations exactly match the deployed version. Mismatch produces silent garbage data.

If a step definition calls a service (not the browser), it will fail on a pure e2e run unless that service is reachable via env vars.

## Hero behat.yml

```yaml
imports:
  - ./behat.yml.dist

default: &default
  extensions: &default_extensions
    Behat\MinkExtension:
      base_url: "https://staging.example.com"
    Oro\Bundle\TestFrameworkBundle\Behat\ServiceContainer\OroTestFrameworkExtension:
      artifacts:
        handlers:
          local:
            directory: '%paths.base%/public/media/behat'
            base_url: ~
            auto_clear: false
```

Start ChromeDriver with Oro's expected endpoint (not selenium-standalone):

```bash
chromedriver --url-base=wd/hub --port=4444
```

## Running Tests

```bash
# Pure e2e against the remote app
php bin/behat --skip-isolators -- path/to/feature.feature

# E2e that also loads Alice fixtures into the remote DB
php bin/behat --skip-isolators-but-load-fixtures -- path/to/feature.feature

# Install Oro's pre-built e2e scenarios and a .behat-secrets.yml.dist template
composer require oro/e2e-tests --dev -n
php bin/behat --skip-isolators -- vendor/oro/e2e-tests/Tests/Behat/Features/create_mailchimp_integration.feature
```

## Secrets

Credentials live in `.behat-secrets.yml` at the application root, referenced from features as `<Secret:login.username>`. **Never commit this file** — a `.behat-secrets.yml.dist` template ships with the `oro/e2e-tests` package. See [secret-management.md](references/secret-management.md).

## Self-Healing

Oro ships Reload Page Healer by default; OpenAI Healer is opt-in. Custom healers implement `HealerInterface` and get the `oro_test.behat.healer` service tag. See [self-healing.md](references/self-healing.md).

## Watch Mode

Interactive development mode pauses on error with a "continue from line N" prompt and a cyclic retry loop. See [watch-mode.md](references/watch-mode.md).

## Key Pitfalls

1. **Running e2e without `--skip-isolators`** — the default isolators try to manage DB state on the remote app: dropping schema, loading fixtures, clearing caches. At best the run aborts; at worst it mutates the target silently.
2. **`.app-env.local` vs `.env-app.test.local`** — Oro's own documentation is internally inconsistent on the env file name. The e2e docs use `.app-env.local` for `ORO_DB_DSN`; the functional-test docs use `.env-app.test.local`. Pick the wrong one and the DSN isn't picked up — the fixture loader silently falls back to no DB. Full explanation in [remote-db.md](references/remote-db.md).
3. **Committing `.behat-secrets.yml`** — once in git history, rotation is the only remedy. Add it to `.gitignore` immediately; only commit the `.dist` template.
4. **Local source mismatch with `--skip-isolators-but-load-fixtures`** — the fixture loader runs locally but writes to the remote DB. If your local migrations differ from the deployed version's schema, rows land in columns that don't exist or skip columns that do. Fixtures appear to load; assertions fail later with baffling data. Always check out the exact deployed tag locally.
5. **Testing production with real customer accounts** — e2e tests mutate state permanently. No rollback exists. Create dedicated test users/customers/orders and never point a run at a real customer's data.

## See Also

- [references/secret-management.md](references/secret-management.md) — `.behat-secrets.yml` schema, `<Secret:>` syntax, installing the e2e tests package
- [references/self-healing.md](references/self-healing.md) — Reload Page Healer, OpenAI Healer config, writing custom healers
- [references/remote-db.md](references/remote-db.md) — env-file naming conflict, `ORO_DB_DSN` format, local-code-matches-deployed warning
- [references/watch-mode.md](references/watch-mode.md) — `--watch` prompt, line numbering, cyclic error recovery
- [references/v6.1.md](references/v6.1.md) — ChromeDriver setup, `oro/e2e-tests` package, artifact handlers
- [references/v7.0.md](references/v7.0.md) — 7.1-dev notes (env-file inconsistency not resolved upstream)
- **oro-behat-testing** skill — integration/functional Behat with full isolation
- **oro-behat-debugging** skill — screenshots, verbose output, Xdebug for failing steps
