# Functional Test — ACL Patterns

ACL tests verify that a user without a permission receives a 403 instead of the resource. Oro uses different assertion helpers for HTML and JSON responses; pick the one that matches the endpoint's content type or the failure message will be misleading.

## HTML 403 Pattern

```php
public function testIndexForbiddenForLimitedUser(): void
{
    $this->client->request(
        'GET',
        $this->getUrl('oro_user_index'),
        [],
        [],
        $this->generateBasicAuthHeader('limited_user@example.com', 'limited_user_password')
    );

    $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 403);
}
```

`assertHtmlResponseStatusCodeEquals` checks the status code and, on failure, **dumps the response body into the failure message**. That is the entire reason to prefer it over plain `assertSame(403, $response->getStatusCode())` on HTML endpoints — when an ACL change unexpectedly returns 200, you want to see the page Oro rendered, not a bare status mismatch.

## JSON 403 Pattern

```php
public function testApiForbidden(): void
{
    $this->client->request(
        'GET',
        $this->getUrl('oro_api_get_users'),
        ['limit' => 100],
        [],
        $this->generateApiAuthHeader('limited_user@example.com')
    );

    $this->assertJsonResponseStatusCodeEquals($this->client->getResponse(), 403);
}
```

`assertJsonResponseStatusCodeEquals` asserts both the status and that `Content-Type` is `application/json`. Use it for every REST API endpoint — it catches the common failure mode where ACL middleware returns a 500 HTML error instead of the JSON error envelope a client expects.

## Auth Header Helpers

- `generateBasicAuthHeader(string $username = 'admin@example.com', string $password = 'admin')` — builds `PHP_AUTH_USER` + `PHP_AUTH_PW` for Symfony's HTTP basic firewall. Used for HTML controller tests.
- `generateApiAuthHeader(string $username)` — builds the `X-WSSE` header the Oro REST API firewall expects. No password needed: the test framework looks up the user's API key and signs the header. This is the reason ACL API tests don't need credentials for each limited user, only the username.

## Limited-User Test Setup

Don't use raw admin headers for ACL tests — create limited users via a fixture and reference them by name:

```php
use Oro\Bundle\UserBundle\Entity\User;

class LoadLimitedUser extends AbstractFixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [LoadOrganization::class, LoadBusinessUnit::class];
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('limited_user@example.com');
        $user->setPlainPassword('limited_user_password');
        $user->setFirstName('Limited');
        $user->setLastName('User');
        $user->setOrganization($this->getReference('organization'));
        $user->addBusinessUnit($this->getReference('business_unit'));
        // ...attach role with no access to the endpoint under test

        $manager->persist($user);
        $manager->flush();
        $this->addReference('limited_user', $user);
    }
}
```

Then in the test, pull the user via reference and pass its email to `generateBasicAuthHeader`/`generateApiAuthHeader`.

## Grid ACL

Datagrids go through the same ACL pipeline as controllers. Forbidden access returns a 403 JSON response:

```php
$response = $this->client->requestGrid('users-grid', [], false, $this->generateApiAuthHeader('limited_user'));
self::assertSame(403, $response->getStatusCode());
```

When permissions are partially granted (read but not edit), the grid returns 200 with restricted rows — assert on the row count in `getJsonResponseContent($response, 200)['data']` instead of the status code.
