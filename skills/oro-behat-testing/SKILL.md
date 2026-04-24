---
name: oro-behat-testing
description: "Use when writing, configuring, debugging, or running Behat integration tests for Oro Commerce 6.1 bundles against a local app — suites, contexts, elements, page objects, fixtures, feature files, or `bin/behat` invocations. Covers suite auto-discovery vs `symfony_bundle` registration, registering custom Element classes (Form, etc.) in `behat.yml`, FeatureContext DI/services.yml wiring, `shared_contexts` propagation between root and bundle-level configs, `behat.yml.dist` vs local `behat.yml` precedence, base_url overrides, formatter flags (`-f pretty -f junit`), `--consumers`, `--strict`, `-s`, `-di`/`-dl` step discovery, Alice YAML fixtures with `@fixture-Bundle:file.yml`, fixture references, Gherkin `Background:` vs fixture tags, `oro_behat_extension`, `OroFeatureContext`/`OroMainContext`, feature-tag mocking (`feature_tag_aware_factory`, `parameters.yml`, paypal/payment stubs), `config_behat_test.yml`, Mink, Chromedriver, isolators. Trigger on phrasings like \"my suite shows 0 features\", \"element not found\", \"step undefined\", \"where do I declare the element\", \"fixture not loading\", \"behat.yml vs behat.yml.dist\", or any local-app Behat test/config task."
---

# OroCommerce v6.1 Behat Integration Testing

Oro extends Behat with auto-discovered suite configuration, element abstractions over Mink, Alice fixture loading, feature-tag-driven service mocking, and automatic database isolation. A bundle's Behat assets live under `Tests/Behat/` — suite config, contexts, elements, page objects, and Alice fixtures. The entry point is the bundle's `Tests/Behat/behat.yml`, which Oro's extension discovers at boot time. No per-project registration is required for the common case.

## Canonical Suite Config

This is the reference `Tests/Behat/behat.yml` combining suite, shared contexts, elements, and a page object:

```yaml
oro_behat_extension:
  shared_contexts:
    - Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\OroMainContext

  suites:
    AcmeDemoBundle:
      contexts:
        - Oro\Bundle\DataGridBundle\Tests\Behat\Context\GridContext
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\FeatureContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features'

  elements:
    Demo Login Form:
      selector: '#login-form'
      class: Oro\Bundle\TestFrameworkBundle\Behat\Element\Form
      options:
        mapping:
          Username: '_username'
          Password: '_password'

  pages:
    Demo Dashboard:
      class: Acme\Bundle\DemoBundle\Tests\Behat\Page\DemoDashboard
      route: 'acme_demo_dashboard'
```

## Two Suite Registration Forms

Oro supports both auto-discovery and manual `symfony_bundle` registration. Auto-discovery (the hero example above) works whenever the suite key matches a registered bundle name and `paths` points to a `@BundleName/...` path inside that bundle. Manual form is necessary when the suite name differs from the bundle, when you want multiple suites in one bundle, or when paths are non-standard:

```yaml
oro_behat_extension:
  suites:
    MyCustomSuite:
      type: symfony_bundle
      bundle: AcmeDemoBundle
      contexts:
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\ImportContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features/Import'
```

See `references/suite-config.md` for the full shape including `shared_contexts`, nested xpath locators, element delegation, and embedded form mapping.

## behat.yml.dist vs behat.yml

The project root `behat.yml.dist` is committed and holds shared defaults (Mink base_url, browser profiles, formatters). A local `behat.yml` is gitignored and overrides per-developer settings — most commonly `base_url` for non-default Docker port mappings and Chrome binary paths. Oro's `bin/behat` loads `behat.yml` if present, otherwise falls back to `behat.yml.dist`. Never commit `behat.yml`; it will break other developers' environments.

A separate but equally important file is `config/config_behat_test.yml` — this is application-level config (not Behat extension config) that takes effect when a feature is tagged `@behat-test-env` and the runner is invoked with `--behat-test-env`. Use it to swap service implementations for mocks or to activate test-only bundles. See `references/feature-tag-mocking.md`.

## Fixtures and References

Alice YAML fixtures live in `Tests/Behat/Features/Fixtures/` and load via a tag on the feature: `@fixture-OroUserBundle:user.yml`. The colon between bundle and filename is mandatory — omitting it silently fails to load the fixture. Query parameters `?user=admin` and `?user_reference=xss_user` apply the fixture under a specific security context. Four references exist without any fixture: `@admin`, `@adminRole`, `@organization`, `@business_unit`. Custom references are registered via a service tagged `oro_behat.reference_repository_initializer`. Details including inline fixtures and cross-bundle `include:` directives in `references/fixtures.md`.

## Running Tests

The canonical invocation matching Oro's own Jenkins pipeline is:

```bash
bin/behat -vv -f pretty -o std -f junit -o var/logs/behat --strict \
  --consumers=2 -s AcmeDemoBundle
```

The dual formatter (`pretty` for humans, `junit` for CI artifacts) is standard — drop it and you lose test result collection in CI. `--strict` fails on undefined or pending steps. `-s AcmeDemoBundle` scopes to one suite. `--consumers=2` runs the MQ consumer layer inside the Behat process — note this coexists with a long-running `consumer` container in a full stack; both layers exist simultaneously. To discover step definitions with full examples use `bin/behat -di -s AcmeDemoBundle | grep -i "cart"`; `-dl` gives the shorter list-only form. See `references/chrome-setup.md` for the Chrome flags required to run headless in Docker and `references/v6.1.md` for the full `oro:install` command Oro's CI uses to provision the test database.

## Feature Tag Mocking

Define candidate mocks in `Tests/Behat/parameters.yml` and use `oro_test.behat.feature_tag_aware_factory` to pick one based on feature/scenario tags. At runtime, a feature tagged `@use-paypal-mock` gets the PayPal stub; an untagged feature falls back to the default. This is the supported way to stub external APIs in Behat — never hand-roll network interception. Details in `references/feature-tag-mocking.md`.

## Key Pitfalls

1. **`@fixture-file.yml` without the bundle prefix** — Alice silently loads nothing, the scenario runs against an empty database, and you chase missing-entity errors instead of the real cause. Always `@fixture-BundleName:file.yml`.
2. **Skipping `shared_contexts` while adding a new suite** — Contexts listed there propagate automatically into every suite. Omit it and your new suite quietly lacks `OroMainContext`, so core steps like "I login as admin" throw UndefinedStep at runtime, not config time.
3. **No Elasticsearch isolator and no MessageQueue isolator by design** — Oro's isolation package resets the database between scenarios but does not reset the search index or the MQ state. Features that index products or emit messages leak state into the next scenario. Order scenarios defensively and flush indices explicitly when it matters.
4. **Raw CSS selectors embedded in step definitions** — works today, fragile tomorrow when the template changes. Define an Element in `behat.yml` and reference it by name; the selector lives in one place and breaks loudly, not in twenty step files.
5. **`--skip-isolators-tag` does not exist in 6.1** — older Oro docs and community posts reference it but the 6.1 CLI rejects it as an unknown option. The current flag is `--skip-isolators` (kills all isolation) for e2e runs; leave isolation on for local integration runs.

## See Also

- `references/suite-config.md` — Both suite registration forms, `shared_contexts`, element YAML with nested xpath and delegation, page objects, `behat.yml.dist` vs `behat.yml`
- `references/fixtures.md` — Alice locations, `@fixture-Bundle:file.yml` syntax, security query params, built-in references, `ReferenceRepositoryInitializer`, inline and cross-bundle fixtures
- `references/feature-tag-mocking.md` — `parameters.yml` factory pattern, `config/config_behat_test.yml` activation, runtime tag resolution
- `references/chrome-setup.md` — ChromeDriver install and flags, Oro Chrome extension mount, headless Docker flags, PHP memory limits
- `references/v6.1.md` — Mailcatcher env vars, `oro:install` invocation, `--consumers` default, isolation gaps
- `references/v7.0.md` — 7.1-dev deltas: PHP 8.1+, Alice `(unique)` deprecation, formal tag-aware mocking, ChromeDriver pinning
