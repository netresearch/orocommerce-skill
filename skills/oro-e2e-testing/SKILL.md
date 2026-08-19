---
name: oro-e2e-testing
description: "Use when running Behat against a DEPLOYED Oro Commerce 6.1 application (staging, QA, prod-clone, prod) rather than a local dev/CI stack: --skip-isolators and --skip-isolators-but-load-fixtures, ORO_DB_DSN placement (.app-env.local vs .env-app.test.local), matching local migrations to the deployed tag, oro/e2e-tests, .behat-secrets.yml and <Secret:>, ChromeDriver url-base/port and Mink 404s, watch mode, Reload Page and OpenAI Healers, HealerInterface with the oro_test.behat.healer tag. Skip for local-only Behat, PHPUnit, k6, or CI on ephemeral containers."
---

# OroCommerce v6.1 End-to-End Behat Testing

E2e drives a real browser against a deployed application: no isolators, Mink + ChromeDriver only. The load-bearing distinction is the flag:

- `--skip-isolators` — **pure e2e.** No DB, cache or container isolation, no fixture loading, no direct service calls. A step definition that calls a service rather than the browser fails here unless that service is reachable.
- `--skip-isolators-but-load-fixtures` — **hybrid.** Fixture loader stays active. Needs `ORO_DB_DSN` at the remote DB **and** local migrations exactly matching the deployed version; a mismatch produces silent garbage data.

## Hero behat.yml

Import `behat.yml.dist`, point `MinkExtension.base_url` at the deployed host, give the artifact handler `auto_clear: false` so screenshots survive. Full block: `references/v6.1.md`.

ChromeDriver must use Oro's expected endpoint, not selenium-standalone's:

```bash
chromedriver --url-base=wd/hub --port=4444
```

## Running Tests

```bash
php bin/behat --skip-isolators -- path/to/feature.feature                    # pure e2e
php bin/behat --skip-isolators-but-load-fixtures -- path/to/feature.feature  # + Alice fixtures

composer require oro/e2e-tests --dev -n   # pre-built scenarios + .behat-secrets.yml.dist
php bin/behat --skip-isolators -- vendor/oro/e2e-tests/Tests/Behat/Features/create_mailchimp_integration.feature
```

## Secrets, healers, watch mode

Credentials sit in `.behat-secrets.yml` at the application root, referenced as `<Secret:login.username>`. **Never commit it** — the `oro/e2e-tests` package ships a `.dist` template ([secret-management.md](references/secret-management.md)).

Reload Page Healer is on by default, OpenAI Healer is opt-in, custom healers implement `HealerInterface` with the `oro_test.behat.healer` tag ([self-healing.md](references/self-healing.md)). Watch mode pauses on error with a "continue from line N" prompt ([watch-mode.md](references/watch-mode.md)).

## Key Pitfalls

1. **Running e2e without `--skip-isolators`** — the isolators try to manage DB state on the *remote* app: dropping schema, loading fixtures, clearing caches. At best the run aborts; at worst it mutates the target silently.
2. **`.app-env.local` vs `.env-app.test.local`** — Oro's own docs disagree: the e2e pages use the first for `ORO_DB_DSN`, the functional-test pages the second. Pick wrong and the DSN is not read; the fixture loader silently falls back to no DB ([remote-db.md](references/remote-db.md)).
3. **Committing `.behat-secrets.yml`** — once in git history, rotation is the only remedy. `.gitignore` it; commit only the `.dist`.
4. **Local source mismatch under `--skip-isolators-but-load-fixtures`** — the loader runs locally and writes to the remote DB, so differing migrations put rows in columns that do not exist or skip ones that do. Fixtures appear to load and assertions fail later with baffling data. Check out the deployed tag.
5. **Production runs against real customer accounts** — e2e mutates state permanently and there is no rollback. Use dedicated test users, customers and orders.

## See Also

In `references/`: `secret-management.md` · `self-healing.md` · `remote-db.md` (the env-file conflict, `ORO_DB_DSN`) · `watch-mode.md` · `v6.1.md` (ChromeDriver, artifact handlers) · `v7.0.md`

Isolated local Behat: **oro-behat-testing**. Failing steps: **oro-behat-debugging**.
