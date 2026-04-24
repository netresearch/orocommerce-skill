# Suite Configuration

## Auto-Discovery Form

A suite key that matches a registered bundle name — and paths pointing at `@BundleName/...` — is picked up automatically by `OroBehatExtension` without any extra metadata. This is the 95% case and should be your default:

```yaml
oro_behat_extension:
  suites:
    AcmeDemoBundle:
      contexts:
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\FeatureContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features'
```

The bundle-relative `@AcmeDemoBundle` syntax is resolved by Symfony's FileLocator at runtime — Oro doesn't hard-code bundle paths.

## Manual `symfony_bundle` Form

When the suite name differs from any registered bundle, or when a single bundle hosts multiple logically distinct suites (e.g. split by feature area), use the explicit form:

```yaml
oro_behat_extension:
  suites:
    AcmeImport:
      type: symfony_bundle
      bundle: AcmeDemoBundle
      contexts:
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\ImportContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features/Import'

    AcmeCheckout:
      type: symfony_bundle
      bundle: AcmeDemoBundle
      contexts:
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\CheckoutContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features/Checkout'
```

Both suites can then be run independently with `bin/behat -s AcmeImport` or `bin/behat -s AcmeCheckout`.

## `shared_contexts` — Inherited Into Every Suite

The top-level `shared_contexts` key under `oro_behat_extension` lists contexts that are injected into every suite Oro discovers, across all bundles. This avoids repeating `OroMainContext` in every single `behat.yml`:

```yaml
oro_behat_extension:
  shared_contexts:
    - Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\OroMainContext
    - Oro\Bundle\FormBundle\Tests\Behat\Context\FormContext

  suites:
    AcmeDemoBundle:
      contexts:
        - Acme\Bundle\DemoBundle\Tests\Behat\Context\FeatureContext
      paths:
        - '@AcmeDemoBundle/Tests/Behat/Features'
```

Shared contexts compose *in addition* to the per-suite `contexts:` list — they are not overridden.

## Elements with Nested XPath Locators

Elements map human-readable names to CSS or XPath selectors. Simple mapping binds a form field to a raw field name; complex mapping uses `type`, `locator`, and optionally `element` to delegate to another Element class:

```yaml
oro_behat_extension:
  elements:
    Payment Method Config Type Field:
      class: Oro\Bundle\PaymentBundle\Tests\Behat\Element\PaymentMethodConfigType

    Payment Rule Form:
      selector: "form[id^='oro_payment_methods_configs_rule']"
      class: Oro\Bundle\TestFrameworkBundle\Behat\Element\Form
      options:
        mapping:
          Method:
            type: 'xpath'
            locator: '//div[@id[starts-with(.,"uniform-oro_payment_methods_configs_rule_method")]]'
            element: Payment Method Config Type Field
          Currency: 'oro_payment_methods_configs_rule[currency]'
```

The `element:` key is delegation: when a step sets the `Method` field, Oro looks up the XPath node, instantiates `PaymentMethodConfigType` around it, and calls its `setValue()`. This keeps complex interaction logic out of step definitions.

## Embedded Form Mapping

Some forms are rendered inside a wrapper div (e.g. contact forms on CMS pages). Declare the wrapper as the Element selector, then use `embedded-id` to tell the Form element which actual form tag to bind to:

```yaml
oro_behat_extension:
  elements:
    CustomContactUsForm:
      selector: 'div#page'
      class: Oro\Bundle\TestFrameworkBundle\Behat\Element\Form
      options:
        embedded-id: embedded-form
        mapping:
          First name: 'custom_bundle_contactus_contact_request[firstName]'
          Email: 'custom_bundle_contactus_contact_request[email]'
```

## Page Objects

Pages wrap a route and give Gherkin access to open and assert on it by name:

```yaml
oro_behat_extension:
  pages:
    User Profile View:
      class: Oro\Bundle\UserBundle\Tests\Behat\Page\UserProfileView
      route: 'oro_user_profile_view'
```

Usage: `And I open User Profile View page` or `And I should be on User Profile View page`. The step definitions in `OroMainContext` handle route resolution.

## `behat.yml.dist` vs `behat.yml`

At the project root (not inside a bundle), `behat.yml.dist` is committed and defines base profiles — Mink base_url, Symfony kernel class, default formatter. A local `behat.yml` is gitignored and overrides per-developer settings. Oro's `bin/behat` prefers the local file if present:

```yaml
# behat.yml.dist — committed
default:
  extensions:
    Behat\MinkExtension:
      base_url: 'http://dev.oro.local'
      browser_name: chrome

# behat.yml — gitignored, per-developer
default:
  extensions:
    Behat\MinkExtension:
      base_url: 'http://dev.oro.local:8080'
```

Never commit `behat.yml`; it will override base_url and browser config for everyone else.
