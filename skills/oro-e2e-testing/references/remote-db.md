# Remote DB Access for Fixture-Loading E2E

When you run `php bin/behat --skip-isolators-but-load-fixtures`, the fixture-loader isolator stays active and tries to connect to the **remote application's database** from your local machine to seed Alice fixtures. This needs two things in sync: a DSN in a local env file, and local source code whose migrations match the deployed version.

## The Env File Naming Conflict

**Oro's own documentation is internally inconsistent about which env file holds `ORO_DB_DSN`.** Both the current 6.1 stable docs and the 7.1-dev master branch disagree with themselves:

- **E2e docs page** — uses `.app-env.local`
- **Functional tests docs page** — uses `.env-app.test.local`

The conflict is **not resolved in 7.1-dev master** as of this writing. Expect to see both forms in blog posts, Stack Overflow answers, and internal team notes. Old Oro docs that predate the split use variations like `.env.test.local` — ignore those entirely.

**For e2e with `--skip-isolators-but-load-fixtures`, follow the e2e docs: use `.app-env.local`.** The functional test path (`.env-app.test.local`) is a different code path used by `--env=test` PHPUnit functional suites, not the e2e Behat flow.

If `ORO_DB_DSN` doesn't appear to take effect:

1. Check file name spelling exactly — the two forms differ by one hyphen position: `.app-env.local` vs `.env-app.test.local`.
2. Make sure the file sits at application root, next to `composer.json`.
3. Try the other name as a diagnostic. If the other name works, you've hit the docs inconsistency on your Oro version — document which one works for your team and stick with it.

There is no runtime error when the DSN is missing. The fixture loader silently falls back to "no DB configured" and your fixtures land nowhere, leaving scenarios to fail at the first data assertion with confusing "user not found" messages.

## DSN Format

```
ORO_DB_DSN=postgres://oro_db_user:oro_db_pass@db.staging.example.com:5432/oro_db
```

- Scheme: `postgres://` (or `postgresql://`) for PostgreSQL, `mysql://` for MySQL legacy mode. OroCommerce 6.1 primarily targets PostgreSQL.
- Host must be reachable from the local machine running Behat — open firewall rules, VPN tunnels, or SSH port forwards as needed.
- Default PostgreSQL port is 5432 (not 3306 — that's MySQL; some sample snippets in Oro docs carry a copy-paste bug with the wrong port).
- URL-encode special characters in the password (`@` becomes `%40`, `:` becomes `%3A`).

## Local Source Must Match Deployed Version

The fixture loader is a **local process** writing to a **remote DB**. It generates SQL from local entity metadata — local Doctrine mappings, local enum definitions, local ExtendEntity state. If the deployed application has ENUM columns, migrations, or ExtendEntity fields that don't exist in your local checkout (or vice versa), the loader writes to a schema it imagines, not the one actually on the remote.

Symptoms of a mismatch:

- "Column does not exist" errors mid-load with columns that clearly exist in the remote DB
- Fixtures load "successfully" but assertions fail because enum IDs, extend columns, or relations point to rows that were never persisted
- Random NULL values where fixture data should be — the loader dropped columns it didn't know about

**Rule:** before running `--skip-isolators-but-load-fixtures`, check out the exact git tag / commit SHA that is deployed on the target. Run `composer install` to match vendor versions. Clear `var/cache/` so Doctrine metadata is rebuilt from the checked-out code.

## Pitfalls

1. **Wrong env file name** — silent DSN fallback, not an error. Try both names when debugging.
2. **Stale local cache after checking out the deployed tag** — Doctrine metadata cache from the previous branch leaks into fixture generation. Always `rm -rf var/cache/*` after switching versions.
3. **Port 3306 in a PostgreSQL DSN** — copy-pasted from a docs example. PostgreSQL is 5432.
4. **URL-encoded `@` inside passwords** — forget to encode and the DSN parser reads the password as a second hostname.
