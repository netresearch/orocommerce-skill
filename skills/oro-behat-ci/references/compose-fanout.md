# Compose Fanout: the Service Mesh Oro Needs

Oro's Behat suite touches HTTP, message queue, search, file storage, and a real browser. Neither Jenkins agents nor GitLab `services:` can model this — you need docker-compose with `include:` fanout.

## The Service List

From `compose-common.yaml` in [oroinc/docker-build](https://github.com/oroinc/docker-build):

| Service | Role | Image | Notes |
|---------|------|-------|-------|
| `behat` | The runner. Executes `bin/behat`. | `${ORO_IMAGE}-test` | Entrypoint is the behat command. |
| `consumer` | Long-running MQ worker for `oro:message-queue:consume`. | `${ORO_IMAGE}-test` | Started before behat; one per queue partition. |
| `operator` | MQ consumer dedicated to specific topics (bulk ops, search reindex). | `${ORO_IMAGE}-test` | Separate from `consumer` so bulk operations don't starve UI-triggered messages. |
| `chrome` | ChromeDriver for `@javascript` steps. | `selenium/standalone-chrome:142.0.7444.175` | Pinned version; extension mounted at `/chrome-extension`. |
| `waf-behat` | WAF proxy between behat and the app. | `${ORO_IMAGE}-test` | Simulates the prod WAF so tests exercise real redirect/header behavior. |
| `install-test` | One-shot installer that runs `oro:install --env=test`. | `${ORO_IMAGE}-test` | Exits after install; `behat` depends on it via `condition: service_completed_successfully`. |
| `functional` | PHPUnit functional test container (separate from behat). | `${ORO_IMAGE}-test` | Same image, different entrypoint. |

## Backing Services (env-driven)

The compose `include:` mechanism pulls in different backing stacks based on env vars:

| Env var | Values | Service files included |
|---------|--------|------------------------|
| `ORO_DB_SERVICE` | `postgres` \| `mysql` | `compose.postgres.yaml` / `compose.mysql.yaml` |
| `ORO_SEARCH_SERVICE` | `elasticsearch` \| `opensearch` | `compose.elasticsearch.yaml` / `compose.opensearch.yaml` |
| `ORO_MQ_SERVICE` | `rabbitmq` \| `dbal` \| `sqs` \| `kafka` | `compose.rabbitmq.yaml`, etc. |
| `ORO_FILE_STORAGE_SERVICE` | `local` \| `s3` \| `gcs` | `compose.s3.yaml`, etc. |
| `ORO_CACHE_SERVICE` | `redis` \| `filesystem` | `compose.redis.yaml` |

This is how one `compose.yaml` spans a full matrix test. In practice most projects pin to one combination (e.g., postgres + opensearch + rabbitmq + local + redis) and only test the matrix on release branches.

## Dependency Graph

```
behat
  ├── install-test  (service_completed_successfully)
  ├── consumer     (service_started)
  ├── operator     (service_started)
  ├── chrome       (service_healthy)
  └── waf-behat    (service_started)

consumer, operator, waf-behat
  ├── postgres     (service_healthy)
  ├── redis        (service_started)
  ├── rabbitmq     (service_healthy)
  └── opensearch   (service_healthy)

install-test
  ├── postgres     (service_healthy)
  ├── rabbitmq     (service_healthy)
  └── opensearch   (service_healthy)
```

The `condition: service_healthy` dependencies require healthchecks on every backing service. Oro's `compose-common.yaml` ships them; don't strip them.

## Fanout Invocation

Root `compose.yaml` looks like:

```yaml
include:
  - path: docker-build/compose/compose-common.yaml
  - path: docker-build/compose/compose-${ORO_DB_SERVICE:-postgres}.yaml
  - path: docker-build/compose/compose-${ORO_MQ_SERVICE:-rabbitmq}.yaml
  - path: docker-build/compose/compose-${ORO_SEARCH_SERVICE:-opensearch}.yaml
```

Env var defaults via `${VAR:-default}` mean a bare `docker compose up behat` works without setting anything. CI pipelines override per matrix cell.

## Why Not Kubernetes?

`include:` has no k8s equivalent. You would need Helm charts with `{{ if .Values.db.postgres }}` conditionals for every backing service — roughly 4–6 weeks of migration work for no behavior improvement. Stick with compose for behat.
