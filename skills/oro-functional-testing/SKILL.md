---
name: oro-functional-testing
description: "Use when writing, debugging, or configuring PHPUnit functional tests for Oro Commerce 6.1 — anything extending WebTestCase: controllers, REST API, console commands (runCommand), datagrids (requestGrid), ACL/403/forbidden flows, PHP or YAML alice fixtures. Trigger on helpers (initClient, loadFixtures, getReference, generateBasicAuthHeader, generateApiAuthHeader, assertHtmlResponseStatusCodeEquals, assertJsonResponseStatusCodeEquals, getJsonResponseContent, getUrl), on annotations (@dbIsolationPerTest, @outputBuffering, @depends), on test-env setup (oro:install --env=test, install_options, .env-app.test.local, ORO_DB_DSN, --user-email ignored), and on transaction/isolation/state-bleed diagnostics between methods or fixtures. Also trigger on Basic vs API auth choice, X-WSSE in tests, admin@example.com defaults, LoadUser/LoadOrganization, @BundleName/...yml paths, EM clearing, InitialFixtureInterface, unit vs functional suite splits, and risky \"test outputs content\" warnings."
---

# OroCommerce v6.1 Functional Testing (PHPUnit)

Functional tests exercise real controllers, services, commands, APIs, and ACL rules against a real database. Oro's `WebTestCase` boots the kernel, manages transactional isolation, loads fixtures deterministically, and layers HTTP/grid/JSON helpers on top of Symfony's `BrowserKit` client.

## Canonical WebTestCase

This is the reference pattern combining `initClient`, fixtures, isolation annotations, and HTML assertions:

```php
<?php
namespace Acme\Bundle\DemoBundle\Tests\Functional\Controller;

use Acme\Bundle\DemoBundle\Entity\Document;
use Acme\Bundle\DemoBundle\Tests\Functional\DataFixtures\LoadDocumentData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolationPerTest
 * @outputBuffering enabled
 */
class DocumentControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->loadFixtures([LoadDocumentData::class]);
    }

    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', $this->getUrl('acme_demo_document_index'));

        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        $this->assertStringContainsString('Documents', $crawler->html());
    }

    public function testView(): void
    {
        /** @var Document $document */
        $document = $this->getReference('document.first');
        $this->client->request('GET', $this->getUrl('acme_demo_document_view', ['id' => $document->getId()]));

        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);
    }
}
```

`initClient(array $kernelOptions = [], array $serverOptions = [])` boots the kernel; pass `generateBasicAuthHeader($user, $pass)` to authenticate (defaults: `admin@example.com` / `admin`). `loadFixtures(array $fixtures, bool $force = false)` loads once per test case; dependencies resolve transitively via `DependentFixtureInterface`. `getReference('name')` retrieves entities registered from fixtures. See [fixtures.md](references/fixtures.md) for entity manager clearing behavior and `InitialFixtureInterface`.

## WebTestCase Helpers

`WebTestCase` provides these instance helpers; memorize the signatures to avoid guessing:

- `initClient(array $kernelOptions = [], array $serverOptions = [])` — boot kernel, optionally authenticate
- `generateBasicAuthHeader(string $username = 'admin@example.com', string $password = 'admin')` — HTML-form basic auth header
- `generateApiAuthHeader(string $username)` — REST API auth header (no password needed; test framework issues a token)
- `loadFixtures(array $fixtures, bool $force = false)` — load PHP or YAML fixtures (`@BundleName/path/to/file.yml`)
- `getReference(string $name)` — fetch an entity registered by a fixture
- `getContainer()` — DI container for pulling services
- `runCommand(string $commandName, array $params)` — execute a console command, returns output string
- `requestGrid(string $gridName, array $gridParams)` — request a datagrid JSON response (called on `$this->client`)
- `getJsonResponseContent(Response $response, int $expectedCode)` — assert status + decode JSON
- `assertHtmlResponseStatusCodeEquals(Response $response, int $expectedCode)` — asserts status, dumps body on failure
- `assertJsonResponseStatusCodeEquals(Response $response, int $expectedCode)` — same for JSON responses
- `getUrl(string $routeName, array $routeParams = [])` — resolve a Symfony route to a URL

## Isolation Annotations

- `@dbIsolationPerTest` — wraps **each test method** in a transaction that rolls back on completion. Without it, every method in the class shares one transaction and state bleeds between tests.
- `@outputBuffering enabled` — required on WebTestCase subclasses so controller output is captured correctly. PHPUnit strict output mode otherwise fails on any echo inside request handling.
- `@depends testMethodName` — inherits both fixtures **and state** from the dependent test. Use sparingly for sequential scenarios (create -> update -> delete) where full isolation would defeat the point of the test.

## Controller, API, Command, Grid

The kernel is reused across all test types; what differs is the helper and the auth header:

```php
// HTML controller
$this->initClient([], $this->generateBasicAuthHeader());
$this->client->request('GET', $this->getUrl('route_name', ['id' => 1]));
$this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

// REST API (back-office)
$this->initClient([], $this->generateApiAuthHeader('admin@example.com'));
$this->client->request('GET', $this->getUrl('oro_api_get_users'));
$result = $this->getJsonResponseContent($this->client->getResponse(), 200);

// Console command — no auth header, still needs initClient()
$output = $this->runCommand('oro:search:reindex', ['--class' => 'OroUserBundle:User']);
$this->assertStringContainsString('Reindex finished', $output);

// Datagrid
$response = $this->client->requestGrid('users-grid', ['users-grid[_filter][username][value]' => 'admin']);
$result = $this->getJsonResponseContent($response, 200);
```

See [grid-testing.md](references/grid-testing.md) for filter bracket notation and [acl-patterns.md](references/acl-patterns.md) for 403 assertion flows.

## Test Environment Installation

Install the test env via `php bin/console oro:install --env=test`. **CLI flags like `--user-email=...` are silently ignored in test env** — functional tests rely on exact values, so install parameters must come from `oro_test_framework.install_options` in `config/config.yml`. See [install-options.md](references/install-options.md) for every field.

Test-specific environment variables live in `.env-app.test.local`:

```
ORO_DB_DSN=postgresql://root@127.0.0.1/crm_test
ORO_MAILER_DSN=smtp://127.0.0.1
```

## Running Tests

```bash
# Functional suite only
docker compose run --rm toolbox bin/phpunit -c ./ --testsuite=functional

# Unit suite only
docker compose run --rm toolbox bin/phpunit -c ./ --testsuite=unit
```

**Never run both suites in one PHPUnit invocation.** Mock objects registered by unit tests interfere with kernel boot and service resolution in functional tests. This is Oro's canonical warning; it typically surfaces as baffling service-not-found or class-not-found errors deep inside seemingly unrelated tests.

## Key Pitfalls

1. **Running unit + functional suites together** — mock objects from unit tests interfere with functional test execution. Always separate runs (`--testsuite=unit` and `--testsuite=functional`).
2. **Forgetting `@dbIsolationPerTest`** — all test methods in the class share one transaction, state bleeds between tests, and failures become order-dependent and hard to reproduce.
3. **Passing `--user-email=...` CLI flags to `oro:install --env=test`** — silently ignored. Test env rejects install command options; the only supported path is `oro_test_framework.install_options` YAML.
4. **Assuming fixtures share entity manager state** — the entity manager is cleared after **each** fixture by default. Entities referenced during fixture A are detached by the time fixture B runs. Implement `InitialFixtureInterface` on fixtures whose entities must survive.
5. **Using bundle aliases for entity FQCNs in tests** (`'OroProductBundle:Product'`) — deprecated in Doctrine; use `Product::class` everywhere.

## See Also

- [install-options.md](references/install-options.md) — Full `oro_test_framework.install_options` block, every field, and the CLI-flags-ignored gotcha
- [fixtures.md](references/fixtures.md) — PHP/YAML fixtures, `DependentFixtureInterface`, `InitialFixtureInterface`, EM clearing, built-in Load* fixtures, references
- [acl-patterns.md](references/acl-patterns.md) — HTML 403 vs JSON 403, `generateBasicAuthHeader` vs `generateApiAuthHeader`, limited-user setup, grid ACL
- [grid-testing.md](references/grid-testing.md) — `requestGrid` signature, filter bracket notation, `getJsonResponseContent`, row extraction
- [v6.1.md](references/v6.1.md) — Symfony 6.4 base, env file naming, unit-vs-functional separation warning
- [v7.0.md](references/v7.0.md) — v7.0 deltas (placeholder)
