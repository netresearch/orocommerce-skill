# WebTestCase helper signatures

Memorising these avoids guessing at call sites; every one is an instance method on `Oro\Bundle\TestFrameworkBundle\Test\WebTestCase`.

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
