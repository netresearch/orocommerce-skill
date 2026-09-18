---
name: oro-behat-ci
description: "Use when running Oro Commerce 6.1 Behat tests in CI/CD — Jenkins (Oro's canonical platform), GitLab CI (community territory per oroinc/platform#954), or any Docker pipeline. Covers compose fanout, headless Chrome, the formatter combo, consumer parallelisation maths, artifacts and init-image state transfer. Also on sight of .gitlab-ci.yml, Jenkinsfile, compose behat stages, compose-common.yaml. Triggers on 'behat ci', 'gitlab ci oro', 'jenkins oro', 'parallel behat', 'chrome headless', 'init-test image', 'behat hang'."
---

# OroCommerce v6.1 Behat in CI/CD

**Oro's public CI support is Jenkins-only.** GitLab CI works but is community territory — [oroinc/platform#954](https://github.com/oroinc/platform/issues/954) has been open since Oro 2.x with no ETA. The canonical Jenkins patterns are scattered across `oroinc/orocommerce-application`, `docker-build` and `environment`; this collects them, plus the GitLab patterns filling the gap.

## Decision: Jenkins or GitLab?

Greenfield: Jenkins, following the oroinc patterns. GitLab already the house default, or an existing compose stack: GitLab in DinD. Init images work on either. Do not reach for GitLab's native `services:` — it cannot model the compose fanout.

## Canonical Formatter Combo

`bin/behat -f pretty -o std -f junit -o var/behat/junit --strict` — `pretty` for humans, `junit` for the report, `--strict` to fail on undefined steps.

## Compose Fanout

`compose-common.yaml` (from [oroinc/docker-build](https://github.com/oroinc/docker-build)) defines the mesh; the root `compose.yaml` fans out via `include:`, driven by `ORO_FILE_STORAGE_SERVICE`, `ORO_SEARCH_SERVICE` and `ORO_MQ_SERVICE`. The `behat` service depends on `consumer`, `operator`, `chrome` and `waf-behat`, runs `ORO_ENV=test` and mounts `./var/behat`; `chrome` is a **pinned** `selenium/standalone-chrome` with Oro's `chrome-extension` mounted read-only (`references/compose-fanout.md`).

## Jenkins, GitLab, and the init images

The canonical `Jenkinsfile` in `oroinc/orocommerce-application` **ships the Behat stage commented out**; enable it per project:

```groovy
sh 'docker compose up --exit-code-from behat behat'
sh 'docker cp $(docker compose ps -q behat):/var/www/oro/var/behat ./var/behat'
```

`docker cp` extracts the artifacts: `--exit-code-from` does not propagate file state. On GitLab: DinD, `docker compose up` from inside, composer and vendor cached.

`oroinc/docker-build` produces `runtime`, `test` (adds Behat, PHPUnit) and `init` / `init-test` with DB and MongoDB pre-loaded — the **cross-job state transfer mechanism**: pull, restore, skip `oro:install`.

Pipeline templates, consumer maths, composer auth, Chrome mount, `ReconnectingConnection`: `references/jenkins-canonical.md`, `references/gitlab-ci.md`.

## Key Pitfalls

1. **GitLab native `services:`** — cannot model the fanout and fails silently on missing dependencies. `docker compose up` inside DinD.
2. **Behat without `--strict`** — undefined and pending steps pass in CI.
3. **Parallel × consumer contention** — N shards at `--consumers=2` is 2N MQ workers on one broker; `parallel: 8` means 16. Plan capacity or drop `--consumers`.
4. **Missing Chrome extension mount** — vanilla `selenium/standalone-chrome` lacks Oro's extension. Symptom: vague JS errors, missing UI hooks.
5. **Composer without the internal GitLab token** — private packages fail to resolve; `composer config gitlab-token.<host> <token>` before `composer install`.
6. **No init-test snapshot** — a fresh `oro:install` per job wastes 5–10 minutes.
7. **ChromeDriver drift** — `:latest` breaks whenever Chrome moves ahead of the driver. Pin the full version and bump deliberately.

## See Also

`references/`: `jenkins-canonical.md` (init-image pattern, GNU-parallel sharding, nightly `@e2esmokeci`) · `gitlab-ci.md` · `compose-fanout.md` · `chrome-headless.md` (flags, driver pinning, `memory_limit=-1`) · `v6.1.md` · `v7.0.md`
