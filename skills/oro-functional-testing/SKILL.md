---
name: oro-functional-testing
description: "Use when writing or debugging PHPUnit functional tests for Oro Commerce 6.1 — anything extending WebTestCase: controllers, REST API, console commands (runCommand), datagrids (requestGrid), ACL/403 flows, alice fixtures. Triggers on initClient, loadFixtures, getReference, generateBasicAuthHeader, generateApiAuthHeader, getJsonResponseContent; on @dbIsolationPerTest, @outputBuffering, @depends; on test-env setup (oro:install --env=test, install_options, .env-app.test.local, ORO_DB_DSN, --user-email ignored); on state bleed between methods or fixtures, EM clearing, InitialFixtureInterface, unit-vs-functional splits."
---

# OroCommerce v6.1 Functional Testing (PHPUnit)

Functional tests exercise real controllers, services, commands, APIs and ACL rules against a real database. `WebTestCase` boots the kernel, manages transactional isolation, loads fixtures deterministically and layers HTTP/grid/JSON helpers over `BrowserKit`.

## Canonical WebTestCase — client, fixtures, isolation, assertion

```php
/**
 * @dbIsolationPerTest
 * @outputBuffering enabled
 */
class DocumentControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());   // admin@example.com / admin
        $this->loadFixtures([LoadDocumentData::class]);
    }

    public function testView(): void
    {
        $document = $this->getReference('document.first');
        $this->client->request('GET', $this->getUrl('acme_demo_document_view', ['id' => $document->getId()]));
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);
    }
}
```

`loadFixtures()` runs once per test case, resolving dependencies via `DependentFixtureInterface`; `getReference()` retrieves what a fixture registered. EM clearing: `references/fixtures.md`.

## WebTestCase Helpers

`generateBasicAuthHeader()` for HTML forms, `generateApiAuthHeader($user)` for the REST API — there the framework issues the token, so no password. Also `loadFixtures`, `getReference`, `getContainer`, `runCommand`, `requestGrid`, `getJsonResponseContent`, `getUrl` and the two status-code assertions. Signatures: `references/webtestcase-helpers.md`.

## Isolation Annotations

- `@dbIsolationPerTest` — a transaction per **method**, rolled back at the end. Without it the class shares one transaction and state bleeds.
- `@outputBuffering enabled` — required on `WebTestCase` subclasses; strict output mode otherwise fails on any echo during request handling.
- `@depends` — inherits fixtures **and state**. Only for sequential scenarios where isolation would defeat the test.

## API, command, grid

Same kernel, different helper and auth header:

```php
$this->initClient([], $this->generateApiAuthHeader('admin@example.com'));   // REST
$result = $this->getJsonResponseContent($this->client->getResponse(), 200);
$output = $this->runCommand('oro:search:reindex', ['--class' => 'OroUserBundle:User']);
$response = $this->client->requestGrid('users-grid', ['users-grid[_filter][username][value]' => 'admin']);
```

`runCommand` needs no auth header, but still `initClient()`. Filter brackets: `references/grid-testing.md`; 403 flows: `references/acl-patterns.md`.

## Test Environment Installation

`php bin/console oro:install --env=test`. **CLI flags such as `--user-email=…` are silently ignored there**, so install parameters must come from `oro_test_framework.install_options` in `config/config.yml` (`references/install-options.md`). `ORO_DB_DSN` and `ORO_MAILER_DSN` live in `.env-app.test.local`.

## Running Tests

```bash
docker compose run --rm toolbox bin/phpunit -c ./ --testsuite=functional
docker compose run --rm toolbox bin/phpunit -c ./ --testsuite=unit
```

**Never both in one invocation** — mocks from the unit suite break kernel boot and service resolution in the functional one, surfacing as service-not-found errors in unrelated tests.

## Key Pitfalls

1. **Forgetting `@dbIsolationPerTest`** — one transaction per class, state bleeding between methods, order-dependent failures.
2. **`--user-email=…` on `oro:install --env=test`** — silently ignored; the only supported path is `oro_test_framework.install_options`.
3. **Assuming fixtures share EM state** — the EM is cleared after **each** fixture, so entities from A are detached when B runs. `InitialFixtureInterface` on those that must survive.
4. **Bundle aliases for entity FQCNs** — deprecated in Doctrine; `Product::class` everywhere.

## See Also

`references/`: `webtestcase-helpers.md` (signatures) · `install-options.md` · `fixtures.md` · `acl-patterns.md` (HTML vs JSON 403, limited-user setup) · `grid-testing.md` · `v6.1.md` · `v7.0.md`
