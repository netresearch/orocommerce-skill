# GitLab CI: the Gap Fill

Oro does not publicly support GitLab CI ([oroinc/platform#954](https://github.com/oroinc/platform/issues/954), open since 2.x). This document is the community-developed pattern set that makes it work anyway.

## Runner Selection

Three options, ranked by maintenance cost:

1. **DinD (`docker:dind` service)** — recommended. The job runs in a `docker:cli` image with a `docker:dind` service; inside the job you run `docker compose up`. Pro: full compose fanout works. Con: nested Docker, slower than bare-metal.
2. **Docker socket mount** — the job mounts `/var/run/docker.sock` from the runner host. Faster than DinD but couples CI jobs to the runner host state. Risky.
3. **Shell runner** — the runner is a bare VM with Docker pre-installed. Fastest, but you hand-manage runner cleanup and cross-job isolation. Only use if you already have a dedicated runner pool.

Do not use `kubernetes` executor for Oro Behat unless you have time to rewrite the compose fanout as Helm charts. The compose `include:` mechanism has no k8s equivalent.

## Why Not GitLab Native `services:`?

GitLab's `services:` keyword can launch per-job sidecar containers but cannot:

- Model `depends_on` with health conditions
- Share volumes between sidecars
- Respect compose `include:` fanout
- Mount files from the job workspace into sidecars

Oro's mesh needs all four. Attempting `services:` ends with silent failures on the first scenario that touches the search index or MQ. Use `docker compose up` inside DinD instead.

## Minimal Pipeline Template

```yaml
behat:
  stage: test
  image: docker:27-cli
  services:
    - name: docker:27-dind
      alias: docker
  variables:
    DOCKER_HOST: tcp://docker:2375
    DOCKER_TLS_CERTDIR: ""
    ORO_IMAGE: registry.example.com/oro/app
    ORO_IMAGE_TAG: ${CI_COMMIT_SHA}
  before_script:
    - apk add --no-cache docker-compose-plugin git
    - docker login -u "$CI_REGISTRY_USER" -p "$CI_REGISTRY_PASSWORD" "$CI_REGISTRY"
    - docker compose pull
  script:
    - docker compose up --exit-code-from behat behat
  after_script:
    - docker cp $(docker compose ps -q behat):/var/www/oro/var/behat ./var/behat || true
    - docker compose down -v
  artifacts:
    when: always
    paths:
      - var/behat/
    reports:
      junit: var/behat/junit/*.xml
    expire_in: 1 week
  parallel: 4
```

## Artifact Collection

Two separate stanzas:

- `artifacts:reports:junit` — parses JUnit XML into GitLab's test report UI. Required for the PR widget's "2 tests failed" summary.
- `artifacts:paths` — captures screenshots, HTML dumps, and the raw JUnit files. Oro writes failure artifacts to `var/behat/` — specifically `var/behat/screenshots/` and `var/behat/html/`.

Both must be inside `when: always` because you need the artifacts when the job fails, which is the common case for debugging.

## Cache Layers

Three levels, cheapest to most expensive:

```yaml
cache:
  - key: composer-${CI_COMMIT_REF_SLUG}
    paths: [.composer-cache/]
  - key: vendor-${CI_COMMIT_REF_SLUG}
    paths: [vendor/]
```

The hard part Oro does not solve with file caches: **Behat needs DB state, not files**. GitLab cache is file-based. Oro solves this via `docker commit` snapshot images (`init-test`) from the `docker-build` repo. Pull the init-test image instead of running `oro:install` in the job.

## Parallelization × Consumer Contention

GitLab `parallel: N` runs N copies of the job. Each job starts its own `consumer` and `operator` compose service. If each runs `--consumers=2`, total MQ workers is `2N`. Concrete:

- `parallel: 4` × `--consumers=2` = **8** workers on one broker
- `parallel: 8` × `--consumers=2` = **16** workers on one broker

RabbitMQ defaults to 100 channels per connection. You hit queue depth contention long before you hit channel limits. Either drop `--consumers=1` or plan broker capacity at `2N * sustained_throughput`.

## Composer Auth for Internal GitLab

Private `vendor/*` and `your-vendor/*` packages are hosted on `your-gitlab-host.example.com`. Composer needs auth:

```yaml
before_script:
  - composer config gitlab-token.your-gitlab-host.example.com "$GITLAB_COMPOSER_TOKEN"
  - composer install --no-interaction --no-progress
```

`$GITLAB_COMPOSER_TOKEN` is a masked CI variable containing a Personal or Project Access Token with `read_api` scope. Without this, `composer install` fails with "could not authenticate against your-gitlab-host.example.com" on the first private package.

## Chrome Extension Mount

Oro loads a custom Chrome extension from `vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension`. It intercepts network requests, injects test hooks, and enables the `@javascript` step definitions. Vanilla `selenium/standalone-chrome` does not ship this extension.

In DinD, the path must be reachable inside the `chrome` container. Either:

1. Copy from the PHP container's vendor tree at compose time via an init container
2. Bind-mount from the job workspace: `./vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension:/chrome-extension:ro`

Option 2 only works if `composer install` runs on the host before compose — which in DinD means running composer in a throwaway container and mounting the result. See the hero snippet in `SKILL.md`.

## `ReconnectingConnection`

Long Behat runs (>30 min) hit MySQL/Postgres's `wait_timeout`, the DB connection goes stale, and the next step fails with "MySQL server has gone away" or "SSL connection has been closed unexpectedly".

Fix: configure doctrine to use a reconnecting wrapper. In `config/config.yml`:

```yaml
doctrine:
  dbal:
    connections:
      default:
        wrapper_class: Oro\Bundle\EntityBundle\Tools\SafeDatabaseChecker
```

(Or equivalent Oro/project-specific reconnecting wrapper — exact class name varies.) The wrapper pings the connection before each query and reconnects on failure. Required for CI runs longer than the DB's idle timeout.

## `behat_test` vs `test` Env Divergence

Oro canonical uses `ORO_ENV=test`. Some projects diverge and use `behat_test` as a separate Symfony env. Document the canonical form here; project-specific overrides belong in project-scope skills. If the project uses `behat_test`, set `ORO_ENV=behat_test` consistently across all compose services — mismatches cause the consumer and the behat runner to see different caches and the tests silently run against stale state.
