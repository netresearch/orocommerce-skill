---
name: oro-behat-testing
description: "Use when writing, configuring or running Behat integration tests for Oro Commerce 6.1 bundles against a LOCAL app — suites, contexts, elements, page objects, Alice fixtures, `bin/behat`. Covers auto-discovery vs `symfony_bundle` registration, `shared_contexts`, `behat.yml.dist` vs `behat.yml`, `--strict`/`--consumers`/`-s`, `@fixture-Bundle:file.yml`, `oro_behat_extension`, `OroMainContext`, feature-tag mocking, `config_behat_test.yml`, Mink, ChromeDriver, isolators. Triggers on \"suite shows 0 features\", \"element not found\", \"step undefined\", \"fixture not loading\"."
---

# OroCommerce v6.1 Behat Integration Testing

Oro adds auto-discovered suites, elements over Mink, Alice fixtures, tag-driven mocking and per-scenario DB isolation. A bundle's assets live under `Tests/Behat/`.

## Canonical suite config — `Tests/Behat/behat.yml`

```yaml
oro_behat_extension:
  shared_contexts: [ ...\Context\OroMainContext ]
  suites:
    AcmeDemoBundle:                    # key = bundle name -> auto-discovered
      contexts: [ ... ]
      paths: [ '@AcmeDemoBundle/Tests/Behat/Features' ]
  elements:
    Demo Login Form: { selector: '#login-form', class: ...\Element\Form }
  pages:
    Demo Dashboard: { class: ...\Page\DemoDashboard, route: acme_demo_dashboard }
```

Element `mapping`, nested xpath and delegation: `references/suite-config.md`.

## Two Suite Registration Forms

Auto-discovery applies when the suite key matches a registered bundle and `paths` points at `@BundleName/…` inside it. Otherwise — different name, several suites per bundle, odd paths — register manually with `type: symfony_bundle` plus `bundle:` (`references/suite-config.md`).

## behat.yml.dist vs behat.yml

`bin/behat` prefers a gitignored local `behat.yml` over the committed `.dist`; never commit the local one. `config/config_behat_test.yml` is **application** config, active only with the `@behat-test-env` tag and `--behat-test-env`.

## Fixtures and References

Alice fixtures sit in `Tests/Behat/Features/Fixtures/`, loaded by a feature tag: `@fixture-OroUserBundle:user.yml`. **The colon is mandatory** — without it nothing loads, silently. `?user=admin` applies it under a security context. Four references need no fixture: `@admin`, `@adminRole`, `@organization`, `@business_unit`; custom ones via `oro_behat.reference_repository_initializer` (`references/fixtures.md`).

## Running Tests

Oro's Jenkins pipeline runs:

```bash
bin/behat -vv -f pretty -o std -f junit -o var/logs/behat --strict \
  --consumers=2 -s AcmeDemoBundle
```

The dual formatter is standard — `pretty` for humans, `junit` for CI; without it CI collects no results. `--strict` fails on undefined or pending steps; `--consumers=2` runs the MQ consumer inside the Behat process, alongside any `consumer` container. Step discovery: `-di` with examples, `-dl` names only. Chrome flags and the test-DB `oro:install`: `references/chrome-setup.md`, `references/v6.1.md`.

Mocking an external API: declare candidates in `Tests/Behat/parameters.yml`, and `oro_test.behat.feature_tag_aware_factory` picks by tag — `@use-paypal-mock` gets the stub. Never hand-roll interception (`references/feature-tag-mocking.md`).

## Key Pitfalls

1. **`@fixture-file.yml` without the bundle prefix** — Alice loads nothing, silently; the scenario runs against an empty database and you chase missing-entity errors.
2. **Skipping `shared_contexts` on a new suite** — without it the suite lacks `OroMainContext`, so "I login as admin" throws UndefinedStep at run time, not config time.
3. **No Elasticsearch or MessageQueue isolator, by design** — the database is reset between scenarios, the search index and MQ state are not, so those features leak into the next scenario.
4. **Raw CSS selectors inside step definitions** — name an Element instead, so a template change breaks one place loudly (`references/suite-config.md`).
5. **`--skip-isolators-tag` does not exist in 6.1** — the CLI rejects it (`references/v6.1.md`).

## See Also

`references/`: `suite-config.md` · `fixtures.md` · `feature-tag-mocking.md` · `chrome-setup.md` · `v6.1.md` · `v7.0.md`
