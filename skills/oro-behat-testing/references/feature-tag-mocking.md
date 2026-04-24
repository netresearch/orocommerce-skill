# Feature-Tag-Aware Mocking

Oro's Behat runner can swap service implementations based on tags on the feature or scenario being executed. This is the supported extension point for stubbing external APIs (payment gateways, shipping rate providers, ERP clients) without hand-rolling network interception.

## The Factory Pattern

Define candidate services in `Tests/Behat/parameters.yml` and use `oro_test.behat.feature_tag_aware_factory` as the factory for the service alias the application code actually depends on:

```yaml
# Tests/Behat/parameters.yml
parameters:
  acme_payment.client.class: Acme\Bundle\PaymentBundle\Client\PaymentClient

services:
  acme_payment.test.client.default_mock:
    class: Acme\Bundle\PaymentBundle\Tests\Behat\Stub\DefaultMockClient

  acme_payment.test.client.paypal_mock:
    class: Acme\Bundle\PaymentBundle\Tests\Behat\Stub\PayPalMockClient

  acme_payment.test.client.stripe_mock:
    class: Acme\Bundle\PaymentBundle\Tests\Behat\Stub\StripeMockClient

  acme_payment.client:
    class: Acme\Bundle\PaymentBundle\Client\PaymentClientInterface
    factory: '@oro_test.behat.feature_tag_aware_factory'
    arguments:
      - '@acme_payment.test.client.default_mock'
      - ['@acme_payment.test.client.paypal_mock', 'use-paypal-mock']
      - ['@acme_payment.test.client.stripe_mock', 'use-stripe-mock']
```

At container build time, the factory reads the currently-running feature's tags (via `Oro\Bundle\TestFrameworkBundle\Behat\BehatFeature` — in 7.1 this is formally documented; in 6.1 it's an implementation detail). The first matching tag wins; untagged features get the first argument (the default).

## Activating the Test Env

The factory only applies when Behat runs with the `--behat-test-env` flag and the feature carries the `@behat-test-env` tag:

```gherkin
@behat-test-env
@use-paypal-mock
Feature: PayPal checkout

  Scenario: Customer pays with PayPal
    Given I am on the checkout page
    When I click "Pay with PayPal"
    Then I should see "Order confirmed"
```

```bash
bin/behat --behat-test-env -s AcmePaymentBundle
```

Features not tagged `@behat-test-env` run against the normal service definitions — mocking is explicitly opt-in.

## `config/config_behat_test.yml`

The application-level config that activates with `--behat-test-env` lives at `config/config_behat_test.yml` (not inside a bundle). Use it to import `parameters.yml` from bundles that need mocking and to toggle test-only bundle services:

```yaml
# config/config_behat_test.yml
imports:
  - { resource: '@AcmePaymentBundle/Tests/Behat/parameters.yml' }
  - { resource: '@AcmeShippingBundle/Tests/Behat/parameters.yml' }

framework:
  mailer:
    dsn: 'smtp://127.0.0.1:1025'
```

This file is not auto-discovered — it must exist at that exact path for Oro to load it.

## Rule: Never Hand-Roll HTTP Interception

Don't reach for cURL wrappers or monkeypatch `HttpClient` in Behat tests. The factory pattern is the supported mechanism. Hand-rolled interception breaks the first time Oro upgrades Symfony HTTP components.
