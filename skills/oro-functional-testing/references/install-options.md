# Functional Test — Install Options

## The Silent Footgun

Oro docs state plainly: **"As functional tests rely on exact values, test environments do not support install command options."** This means CLI flags passed to `oro:install --env=test` — like `--user-email=...`, `--user-password=...`, `--application-url=...` — are silently ignored. The command runs, reports success, and installs with defaults that your tests then fail to match.

The only supported path to customize the test install is the `oro_test_framework.install_options` block in `config/config.yml`.

## Full Install Options Block

```yaml
# config/config.yml
oro_test_framework:
    install_options:
        user_name: admin
        user_email: admin@example.com
        user_firstname: John
        user_lastname: Doe
        user_password: admin
        sample_data: false
        organization_name: OroInc
        application_url: http://localhost/
        skip_translations: true
        timeout: 600
        language: en
        formatting_code: en_US
```

## Field Reference

- `user_name` — Admin username created by install.
- `user_email` — Admin email. Must match what `generateBasicAuthHeader()` defaults to (`admin@example.com`) unless every test passes an override.
- `user_firstname` — Admin first name; appears in seeded user records that fixtures may reference.
- `user_lastname` — Admin last name.
- `user_password` — Admin password, i.e. the default test user password used by `generateBasicAuthHeader()`.
- `sample_data` — Keep `false` for functional tests. Sample data adds nondeterministic rows that break fixture-based assertions.
- `organization_name` — Name of the default organization record. Most ownership tests reference this seeded org.
- `application_url` — Used to build absolute URLs in the test kernel; match the scheme/host your tests expect.
- `skip_translations` — Keep `true`. Skipping translation dumps speeds the install and avoids locale-dependent assertions.
- `timeout` — Install command timeout in seconds; raise on slow CI runners.
- `language` — UI language code for the seeded user (e.g. `en`).
- `formatting_code` — Locale code for number/date formatting (e.g. `en_US`). Controls how fixture-loaded dates and prices render in HTML assertions.

## Running the Install

```bash
docker compose run --rm toolbox bin/console oro:install --env=test
```

No CLI flags. Everything the test env needs comes from the YAML block above. The install command reads `oro_test_framework.install_options` and applies each field during bootstrap.

## Why CLI Flags Are Ignored

The test framework intercepts install in the `test` env and overrides parameters with the values from `install_options` so that `generateBasicAuthHeader()`, fixture-referenced org/user records, and URL generators all see the same fixed values across every test run and every developer machine. Allowing CLI flags would let per-invocation drift sneak into fixture references and break the isolation guarantees the test suite depends on.

## When Tests Fail With Auth Errors

If `generateBasicAuthHeader()` returns 401 after a fresh test install, the mismatch is almost always between the hardcoded defaults (`admin@example.com` / `admin`) and a modified `install_options` block. Either revert the YAML or pass the custom credentials to every `generateBasicAuthHeader($email, $password)` call — there is no middle ground, because the helper's defaults are compiled into test base classes throughout Oro.
