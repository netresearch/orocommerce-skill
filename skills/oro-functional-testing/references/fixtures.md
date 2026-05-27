# Functional Test — Data Fixtures

Oro functional fixtures populate the database with deterministic data before each test case. They come in two flavors (PHP class and YAML) and plug into a dependency-resolving loader that tracks references across fixtures.

## PHP Fixture

```php
<?php
namespace Acme\Bundle\DemoBundle\Tests\Functional\DataFixtures;

use Acme\Bundle\DemoBundle\Entity\Document;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;

class LoadDocumentData extends AbstractFixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [LoadOrganization::class, LoadUser::class];
    }

    public function load(ObjectManager $manager): void
    {
        $document = new Document();
        $document->setTitle('First document');
        $document->setOwner($this->getReference('user'));
        $document->setOrganization($this->getReference('organization'));

        $manager->persist($document);
        $manager->flush();

        $this->addReference('document.first', $document);
    }
}
```

Dependencies resolve transitively: declaring `LoadOrganization::class` pulls in any fixture it depends on, so the common `LoadUser` + `LoadOrganization` base is always available.

## YAML Fixture

```yaml
# Tests/Functional/DataFixtures/documents.yml
Acme\Bundle\DemoBundle\Entity\Document:
    document.alpha:
        title: Alpha
        owner: '@user'
        organization: '@organization'
    document.beta:
        title: Beta
        owner: '@user'
        organization: '@organization'
```

Load it alongside PHP fixtures by passing a bundle-relative path:

```php
$this->loadFixtures([
    LoadOrganization::class,
    '@AcmeDemoBundle/Tests/Functional/DataFixtures/documents.yml',
]);
```

## Built-in Load* Fixtures

Three built-in fixtures seed the minimum identity graph Oro expects:

- `Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization`
- `Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadBusinessUnit`
- `Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser`

Any fixture that creates an entity with USER or BUSINESS_UNIT ownership should declare these as dependencies — otherwise `getReference('user')`/`getReference('organization')` return nothing and persistence fails on NOT NULL columns.

## Entity Manager Clearing (the important nuance)

**By default, the entity manager is cleared after loading EACH fixture** — not after the batch. That means any entity your fixture persisted becomes detached the moment the next fixture starts. Rationale: identity map reset between fixtures avoids stale references and lets each fixture start from a clean slate.

Consequences:

- Inside a single fixture, `persist()` + `flush()` + `addReference()` work as expected.
- Across fixtures, references survive (they are tracked by name), but the underlying entity object may need to be re-fetched. Use `getReference()` to get a managed copy in the current EM.
- Bulk data fixtures that rely on the entity remaining managed across fixture boundaries must implement `Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\InitialFixtureInterface` — this tells the loader "don't clear the EM after me."

```php
use Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\InitialFixtureInterface;

class LoadCoreReferenceData extends AbstractFixture implements InitialFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Entities persisted here stay managed for all subsequent fixtures.
    }
}
```

Use `InitialFixtureInterface` sparingly. It is for built-in/reference data that every other fixture assumes is present and managed (enum options, currencies, localization); for regular per-test data, the default clearing is what you want.

## Accessing References In Tests

```php
protected function setUp(): void
{
    $this->initClient([], $this->generateBasicAuthHeader());
    $this->loadFixtures([LoadDocumentData::class]);
}

public function testSomething(): void
{
    /** @var Document $document */
    $document = $this->getReference('document.first');
    // $document is a managed entity in the current EM
}
```

## The `$force` Parameter

`loadFixtures(array $fixtures, bool $force = false)` — fixtures load **once per test case** by default. The loader tracks which fixtures have been loaded and short-circuits on repeat calls. Pass `$force = true` only when a single test method must reload fixtures mid-run (e.g. after destructive operations in a `@depends` chain). Abuse of `$force` slows the suite dramatically; prefer `@dbIsolationPerTest` to rollback changes instead of reloading.
