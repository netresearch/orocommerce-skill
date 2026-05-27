# Jenkins: Oro's Canonical CI Patterns

Oro publishes its CI patterns exclusively for Jenkins. The pieces are scattered across three oroinc repos:

- **[oroinc/orocommerce-application](https://github.com/oroinc/orocommerce-application)** — the reference `Jenkinsfile` at repo root
- **[oroinc/docker-build](https://github.com/oroinc/docker-build)** — `compose-common.yaml` and the image build scripts
- **[oroinc/environment](https://github.com/oroinc/environment)** — `ci/behat.sh`, the legacy sharding runner

None of these repos document a complete working example in one place. The patterns below are the consolidation.

## The Behat Stage (Shipped Commented Out)

The canonical `Jenkinsfile` ships the Behat stage **committed but commented out**. Projects enable it by removing the comment markers. Minimal shape:

```groovy
stage('Behat') {
  steps {
    sh '''
      docker compose up --exit-code-from behat behat
    '''
  }
  post {
    always {
      sh 'docker cp $(docker compose ps -q behat):/var/www/oro/var/behat ./var/behat || true'
      junit 'var/behat/junit/*.xml'
      archiveArtifacts artifacts: 'var/behat/**', allowEmptyArchive: true
    }
  }
}
```

`docker cp` is the artifact extraction mechanism. `--exit-code-from` propagates the exit status but not file state — volumes work, but in Jenkins agents without a persistent workspace mount you must `docker cp` after the run.

## Compose Fanout

The `compose.yaml` in `orocommerce-application` uses Docker Compose 2.x `include:` to fan out service definitions from `compose-common.yaml` (in `docker-build`). The fanout is driven by env vars read at compose time:

- `ORO_FILE_STORAGE_SERVICE` — `local` | `s3` | `gcs`
- `ORO_SEARCH_SERVICE` — `elasticsearch` | `opensearch`
- `ORO_MQ_SERVICE` — `rabbitmq` | `dbal` | `sqs` | `kafka`

This means one `compose.yaml` produces a different service mesh per CI matrix cell. See `compose-fanout.md` for the full list.

## Image Types

`docker-build` produces four image variants off the same Dockerfile, distinguished by build args and entrypoint:

| Image | Purpose | Env |
|-------|---------|-----|
| `runtime` | CLI / PHP-FPM / web for production | `prod` |
| `test` | adds Behat + PHPUnit + dev tools | `test` |
| `init` | prod DB + MongoDB pre-loaded, ready to run | `prod` |
| `init-test` | prod DB + MongoDB pre-loaded, ready to run | `test` |

**The init images are the cross-job state transfer mechanism.** Full `oro:install` takes 5–10 min. Instead:

1. A nightly (or on-merge) job runs `oro:install` once and `docker commit`s the running container.
2. The commit becomes the `init-test` image, pushed to the registry.
3. Every PR job pulls `init-test` and starts from a ready-to-test DB state.

This is the single biggest CI speedup Oro ships. It's also why file-based caches do not help — Oro's state lives in Postgres and MongoDB, not on disk.

## Parallelization (Legacy GNU-Parallel)

`oroinc/environment/ci/behat.sh` contains the reference sharding script. Shape:

```bash
find tests/Behat/Features -name '*.feature' \
  | parallel -j "${BEHAT_JOBS:-4}" --line-buffer \
    'bin/behat --strict -f pretty -o std -f junit -o var/behat/junit-{%}.xml {}'
```

Each worker gets a unique `--profile` and its own DB template via the `docker commit` install-snapshot trick. Note: `docker-build/scripts` does **not** contain a Behat execution script — only `test_behat_wiring_cs.sh`, which lints feature file code style. You write the runner yourself.

## Nightly Default

The nightly Jenkins job runs:

```bash
bin/behat --strict --skip-isolators --tags=@e2esmokeci
```

`--skip-isolators` disables the per-scenario state isolators (feature isolator, messaging isolator, search index isolator). This is **smoke e2e mode** — fast, non-isolated, not a full test run. Full isolated runs are reserved for release branches.

## Service Dependencies

The `behat` service in `compose-common.yaml` declares:

```yaml
depends_on:
  consumer:    { condition: service_started }
  operator:    { condition: service_started }
  chrome:      { condition: service_healthy }
  waf-behat:   { condition: service_started }
```

`chrome` uses `service_healthy` because selenium-standalone exposes a healthcheck on `/status`. Missing the healthcheck means behat starts before ChromeDriver is ready and dies with "unable to connect to the remote server" on the first JS step.

## Canonical Env Vars

- `ORO_ENV=test` — canonical Behat env
- `ORO_INSTALL_TIMEOUT=3600` — `oro:install` often exceeds the Symfony default timeout
- `ORO_BEHAT_ARGS` — project-specific args appended to the behat command
