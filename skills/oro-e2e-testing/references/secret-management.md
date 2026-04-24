# Secret Management for E2E Tests

E2e tests authenticate against real applications, which means real credentials have to reach Behat somehow. Oro's e2e framework reads them from a YAML file at the application root, never from behat.yml itself.

## File Location and Template

```
<app-root>/.behat-secrets.yml       # real secrets — NEVER commit
<app-root>/.behat-secrets.yml.dist  # template — safe to commit
```

The `oro/e2e-tests` package installs the `.dist` template:

```bash
composer require oro/e2e-tests --dev -n
cp .behat-secrets.yml.dist .behat-secrets.yml
# edit .behat-secrets.yml with real values
```

Add the real file to `.gitignore` immediately — before the first `git add`:

```
.behat-secrets.yml
```

## Schema

```yaml
secrets:
  login:
    username: admin
    password: s3crEtPas$
  api:
    token: abc123
  mailchimp:
    api_key: xyz-us12
```

The structure under `secrets:` is free-form — arrange keys to suit your scenarios. Dotted paths in feature files dereference nested keys.

## Referencing Secrets in Features

Use `<Secret:dotted.path>` placeholders anywhere a step argument appears:

```gherkin
Feature: Admin login on staging
  Scenario: Log in with the deployment account
    Given I go to "admin"
    And I fill form with:
      | Username | <Secret:login.username> |
      | Password | <Secret:login.password> |
    And I click "Log in"
    Then I should see "Welcome"
```

Behat substitutes `<Secret:login.username>` with `secrets.login.username` from the YAML before the step executes. The substitution runs before table expansion, so placeholders work inside table cells, quoted arguments, and scenario outlines alike.

## Scope

- One `.behat-secrets.yml` per application root. Multi-suite runs share the same file.
- Values are strings only. Structured data (lists, maps as values) is not supported by the `<Secret:>` resolver — flatten nested maps by adding more key levels.
- The file is read once at Behat startup. Editing it mid-run has no effect.

## Pitfalls

1. **Committing the real file** — `git log -p -- .behat-secrets.yml` is the first thing an attacker checks. If committed even once, rotate every credential and force-push the history. Use the `.dist` template for anything that ships in git.
2. **Placing the file anywhere but the application root** — the resolver does not search subdirectories. `tests/Behat/.behat-secrets.yml` is silently ignored.
3. **YAML syntax errors with special chars in passwords** — wrap values containing `$`, `:`, `#`, or `@` in single quotes: `password: 's3crEtPas$'`.
