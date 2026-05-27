---
name: oro-behat-ci
description: "Use when running Oro Commerce 6.1 Behat tests in CI/CD — whether on Jenkins (Oro's canonical platform), GitLab CI (community territory, since Oro does not publicly support it per oroinc/platform#954), or any Docker-based pipeline. Covers compose fanout for Oro's service mesh, Chrome headless configuration, canonical formatter combo, consumer parallelization math, artifact collection, and init-image state transfer. Also use when you see .gitlab-ci.yml, Jenkinsfile, docker-compose behat stages, or compose-common.yaml files in Oro projects. Relevant when the user mentions 'behat ci', 'gitlab ci oro', 'jenkins oro', 'pipeline oro', 'parallel behat', 'junit output', 'oro chrome headless', 'consumer cleanup', 'init-test image', 'behat hang', 'compose fanout', or any CI-related Oro Behat task."
---

# OroCommerce v6.1 Behat in CI/CD

**Oro's public CI support is Jenkins-only.** GitLab CI works but is community territory — the upstream issue [oroinc/platform#954](https://github.com/oroinc/platform/issues/954) has been open since Oro 2.x with no ETA. This skill consolidates the canonical Jenkins patterns (scattered across `oroinc/orocommerce-application`, `oroinc/docker-build`, `oroinc/environment`) and the community-developed GitLab CI patterns that fill the gap.

## Decision: Jenkins or GitLab?

| Situation | Use |
|-----------|-----|
| Greenfield Oro CI, no prior commitment | Jenkins — follow oroinc canonical patterns, lowest maintenance cost |
| Enterprise default is GitLab | GitLab CI in DinD with `docker compose up`, see `references/gitlab-ci.md` |
| Small dev-only CI with compose stack already | GitLab with basic compose invocation |
| Need init-image snapshot fast paths | Either — both platforms pull from the same `docker-build` registry |

Do not attempt GitLab native `services:` keyword for Oro. It cannot model the compose fanout.

## Canonical Formatter Combo

Always run both formatters — `pretty` for humans tailing logs, `junit` for the CI report:

```bash
bin/behat \
  -f pretty -o std \
  -f junit  -o var/behat/junit \
  --strict
```

`--strict` fails the build on undefined and pending steps. Without it, skeleton steps accumulate silently as tech debt.

## Hero Compose Snippet

Oro's mesh requires multiple services wired together. A `compose-common.yaml` (from [oroinc/docker-build](https://github.com/oroinc/docker-build)) defines them; the root `compose.yaml` fans out via `include:` driven by `ORO_FILE_STORAGE_SERVICE`, `ORO_SEARCH_SERVICE`, `ORO_MQ_SERVICE` env vars.

```yaml
services:
  behat:
    image: ${ORO_IMAGE}:${ORO_IMAGE_TAG}-test
    depends_on: [consumer, operator, chrome, waf-behat]
    environment:
      ORO_ENV: test
      ORO_DB_HOST: postgres
    volumes:
      - ./var/behat:/var/www/oro/var/behat
    command: >
      bin/behat -f pretty -o std -f junit -o var/behat/junit --strict

  consumer:
    image: ${ORO_IMAGE}:${ORO_IMAGE_TAG}-test
    command: bin/console oro:message-queue:consume --env=test

  operator:
    image: ${ORO_IMAGE}:${ORO_IMAGE_TAG}-test
    command: bin/console oro:message-queue:consume --env=test

  chrome:
    image: selenium/standalone-chrome:142.0.7444.175
    volumes:
      - ./vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension:/chrome-extension:ro

  waf-behat:
    image: ${ORO_IMAGE}:${ORO_IMAGE_TAG}-test
```

Full service list and rationale in `references/compose-fanout.md`.

## Jenkins Canonical Pattern

The canonical `Jenkinsfile` in `oroinc/orocommerce-application` **commits the Behat stage commented out** — you enable it per-project. Pattern:

```groovy
stage('Behat') {
  sh 'docker compose up --exit-code-from behat behat'
  sh 'docker cp $(docker compose ps -q behat):/var/www/oro/var/behat ./var/behat'
}
```

`docker cp` is the artifact extraction mechanism — `--exit-code-from` does not propagate file state. Full details in `references/jenkins-canonical.md`.

## GitLab CI — the Gap Fill

Use DinD (`docker:dind` service), run `docker compose up` from inside. Cache composer, vendor, and pull the init-test image for DB state. Full pipeline template, artifact config, consumer math, composer GitLab auth, Chrome extension mount, and `ReconnectingConnection` notes in `references/gitlab-ci.md`.

## Init / Init-Test Images

`oroinc/docker-build` produces four image variants:

- `runtime` — CLI / PHP-FPM / web
- `test` — adds Behat + PHPUnit + tools
- `init` — prod DB + MongoDB pre-loaded (`--env=prod`)
- `init-test` — prod DB + MongoDB pre-loaded (`--env=test`)

The init images are the **cross-job state transfer mechanism**. Pull the image, restore pre-baked DB state, skip full `oro:install` (5–10 min per job). See `references/jenkins-canonical.md`.

## Key Pitfalls

1. **GitLab native `services:` for Oro's mesh** — cannot model the fanout; fails silently on missing dependencies. Use `docker compose up` inside DinD.
2. **Running Behat without `--strict`** — undefined and pending steps silently pass in CI while accruing skeleton debt.
3. **Parallel × consumer contention** — N GitLab shards times `--consumers=2` each equals 2N MQ workers competing on one broker. With `parallel: 8` and 2 consumers per job that is 16 workers; plan broker capacity or drop `--consumers`.
4. **Missing Chrome extension mount** — vanilla `selenium/standalone-chrome` lacks Oro's custom extension at `vendor/oro/platform/src/Oro/Bundle/TestFrameworkBundle/Resources/chrome-extension`. Symptom: vague JS errors, missing UI hooks. Mount read-only from the PHP container's vendor tree.
5. **Composer install without internal GitLab token** — private `vendor/*` and `your-vendor/*` packages fail to resolve. Configure `composer config gitlab-token.your-gitlab-host.example.com <token>` in the job before `composer install`.
6. **No init-test image snapshot** — running `oro:install` fresh in every job wastes 5–10 min per build. Use `docker commit` or pull the pre-baked `init-test` image for state transfer.
7. **ChromeDriver drift** — using `:latest` breaks when Chrome updates and the driver lags. Pin the full version (e.g., `142.0.7444.175`) and bump deliberately.

## See Also

- [jenkins-canonical.md](references/jenkins-canonical.md) — oroinc sources, Behat stage, init-image pattern, GNU-parallel sharding, nightly `@e2esmokeci` default
- [gitlab-ci.md](references/gitlab-ci.md) — DinD pipeline, artifacts, cache layers, parallelization math, composer auth, Chrome mount, `ReconnectingConnection`
- [compose-fanout.md](references/compose-fanout.md) — the service mesh Oro needs, env-var-driven fanout
- [chrome-headless.md](references/chrome-headless.md) — mandatory flags with rationale, ChromeDriver pinning, `memory_limit=-1`
- [v6.1.md](references/v6.1.md) — 6.1-stable CI specifics
- [v7.0.md](references/v7.0.md) — 7.x CI notes (placeholder)
