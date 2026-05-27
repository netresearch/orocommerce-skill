# Fixtures and Entity References

## Alice File Location

Fixture files live under `{Bundle}/Tests/Behat/Features/Fixtures/` and use Nelmio Alice YAML syntax. Oro's `OroAliceLoader` wraps Alice with entity reference management, security context injection, and cross-bundle includes.

```yaml
# src/Acme/Bundle/DemoBundle/Tests/Behat/Features/Fixtures/document.yml
Acme\Bundle\DemoBundle\Entity\Document:
  document_{1..3}:
    title: 'Document <current()>'
    owner: '@admin'
    organization: '@organization'
```

## Loading Fixtures via Feature Tag

A feature-level tag loads the fixture before any scenario runs. **The colon between bundle name and filename is mandatory.** Without the bundle prefix Alice silently loads nothing:

```gherkin
@fixture-AcmeDemoBundle:document.yml
Feature: Document list

  Scenario: Admin sees all documents
    Given I login as administrator
    And I go to Documents
    Then I should see 3 records
```

Stacking multiple fixture tags on one feature is supported and they load in declaration order:

```gherkin
@fixture-OroUserBundle:user.yml
@fixture-OroOrganizationBundle:BusinessUnit.yml
@fixture-AcmeDemoBundle:document.yml
Feature: Multi-fixture setup
```

## Security Context Query Parameters

Append `?user=admin` or `?user_reference=xss_user` to run the fixture load under a specific user context (affects ownership and ACL-driven defaults):

```gherkin
@fixture-OroSecurityTestBundle:commerce/shopping-list.yml?user_reference=xss_user
@fixture-OroSecurityTestBundle:commerce/saved-search.yml?user=admin
Feature: ACL-scoped fixtures
```

`user=` takes a username string; `user_reference=` takes an Alice reference name (from a prior fixture or a built-in).

## Built-In References

Four references exist automatically, no fixture required:

- `@admin` — the admin user created by `oro:install`
- `@adminRole` — the administrator role
- `@organization` — the default organization
- `@business_unit` — the default business unit

Use them directly in any fixture file or inline fixture without declaring them first.

## Registering Custom References

For a custom reference that must be available across many fixtures (e.g. a payment method, a product family), implement `Oro\Bundle\TestFrameworkBundle\Behat\Fixtures\ReferenceRepositoryInitializer` and tag the service:

```php
namespace Acme\Bundle\DemoBundle\Tests\Behat\Fixtures;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\TestFrameworkBundle\Behat\Fixtures\ReferenceRepositoryInitializer;
use Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\Collection;

class LoadDemoReferences implements ReferenceRepositoryInitializer
{
    #[\Override]
    public function init(ManagerRegistry $registry, Collection $referenceRepository): void
    {
        $family = $registry->getRepository(AttributeFamily::class)
            ->findOneBy(['code' => 'default_family']);
        $referenceRepository->set('default_family', $family);
    }
}
```

```yaml
# Tests/Behat/services.yml
services:
  Acme\Bundle\DemoBundle\Tests\Behat\Fixtures\LoadDemoReferences:
    tags:
      - { name: oro_behat.reference_repository_initializer }
```

Fixtures then refer to `@default_family` without any per-fixture setup.

## Inline Fixtures in Gherkin

For small, feature-local data, skip Alice files entirely and use inline step definitions. Oro ships `OroFixtureLoader` steps that understand tables and Faker inline functions:

```gherkin
Given the following contacts:
  | First Name | Last Name | Email          |
  | Joan       | Anderson  | <email()>      |
  | Karl       | Smith     | <firstName()>@example.com |

And I have 5 Cases
And there are two users with their own 7 Accounts
```

`<email()>`, `<firstName()>`, `<lastName()>`, `<phoneNumber()>`, `<company()>` are Faker inline functions Alice evaluates at load time. Older Oro docs show `username (unique): foo` — use plain `<firstName()>` in new fixtures; the `(unique)` suffix is deprecated in 7.x.

## Cross-Bundle Includes

One fixture can `include:` another from a different bundle. This is the idiomatic way to reuse a canonical customer or catalog setup:

```yaml
# Tests/Behat/Features/Fixtures/checkout-setup.yml
include:
  - '@OroCustomerBundle/Tests/Behat/Features/Fixtures/CustomerUserAmandaRCole.yml'
  - '@OroPricingBundle/Tests/Behat/Features/Fixtures/PriceList.yml'

Acme\Bundle\DemoBundle\Entity\Order:
  order_1:
    customer: '@amanda_r_cole'
    priceList: '@default_price_list'
```

The `@BundleName/...` prefix is resolved via Symfony's FileLocator, same as suite paths.
