# Architecture

## System Overview

This plugin provides 14 domain-specific skills for OroCommerce v6.1 development and testing, packaged as a Claude Code plugin. Each skill targets a distinct developer intent (e.g., "I need to create a datagrid" triggers `oro-datagrid`, "my behat test is flaky" triggers `oro-behat-debugging`) and delivers version-pinned code generation guidance, configuration patterns, and anti-pattern warnings.

## Component Map

Development skills (8):

| Skill | Responsibility | Key File |
|-------|---------------|----------|
| `oro-api` | REST API via `api.yml` / `api_frontend.yml`, processors | `skills/oro-api/SKILL.md` |
| `oro-bundle` | Bundle scaffolding, DI, services, navigation, system config | `skills/oro-bundle/SKILL.md` |
| `oro-datagrid` | Datagrid YAML config, columns, filters, actions | `skills/oro-datagrid/SKILL.md` |
| `oro-entity` | Entities, ownership, ExtendEntity, migrations | `skills/oro-entity/SKILL.md` |
| `oro-frontend` | Themes, SCSS, Twig layouts, Chaplin.js | `skills/oro-frontend/SKILL.md` |
| `oro-integration` | Message queue, import/export, connectors, cron | `skills/oro-integration/SKILL.md` |
| `oro-security` | ACL, permissions, access rules, field ACL | `skills/oro-security/SKILL.md` |
| `oro-workflow` | Workflows, transitions, checkout customization | `skills/oro-workflow/SKILL.md` |

Testing skills (6):

| Skill | Responsibility | Key File |
|-------|---------------|----------|
| `oro-behat-testing` | Behat integration — suites, contexts, elements, Alice fixtures, feature-tag mocking | `skills/oro-behat-testing/SKILL.md` |
| `oro-behat-debugging` | Debug failing/flaky Behat — Xdebug split CLI+FPM, verbosity, AJAX race analysis, tmpfs tuning | `skills/oro-behat-debugging/SKILL.md` |
| `oro-behat-ci` | Behat/PHPUnit in CI — Jenkins canonical, GitLab CI community patterns, DinD, compose fanout | `skills/oro-behat-ci/SKILL.md` |
| `oro-e2e-testing` | End-to-end Behat against deployed apps — `--skip-isolators`, secrets, healers, watch mode | `skills/oro-e2e-testing/SKILL.md` |
| `oro-functional-testing` | PHPUnit functional tests — `WebTestCase`, fixtures, ACL/API/command/grid testing | `skills/oro-functional-testing/SKILL.md` |
| `oro-k6-testing` | k6 performance and load tests — 14 custom Oro metrics, warm-up, storefront/checkout scripts | `skills/oro-k6-testing/SKILL.md` |

## Dependency Rules

- **Skills are independent**: No skill imports from or depends on another skill at runtime.
- **Testing skills cross-reference in See Also sections**: `oro-behat-testing`, `oro-behat-debugging`, `oro-e2e-testing`, `oro-behat-ci` point at each other as related reading. These are pointers, not imports — each skill still delivers value standalone.
- **References are per-skill**: Each skill's `references/` directory contains only content relevant to that skill.
- **Progressive disclosure**: Plugin metadata triggers skill selection. `SKILL.md` loads on activation. `references/*.md` files are read on demand for deeper detail.

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| **v6.1 pinned** | All examples use PHP 8 attributes (not annotations), `#[\Override]`, Oro's proprietary MQ (not Symfony Messenger), and v6.1 directory conventions. v7.0 placeholders exist in `references/version-notes.md` (development skills) and `references/v7.0.md` (testing skills). |
| **14-skill split** | 8 development + 6 testing. Each skill corresponds to a recognizable development or testing task so triggering stays precise. |
| **Lean skills (<500 lines)** | Keeps token usage low on activation. Detailed reference material lives in `references/` and is loaded only when needed. |
| **Testing split into 6 rather than 1** | Behat integration, Behat debugging, e2e, PHPUnit functional, k6 performance, and CI each have distinct triggering contexts and distinct reference material. A single `oro-testing` skill would over-trigger on unrelated queries and under-serve on depth. |
| **Split licensing** | Code/tooling under MIT for maximum reuse; skill content under CC BY-SA 4.0 to ensure attribution and share-alike for knowledge artifacts. |
| **Audience: Oro-experienced devs** | Skills assume Symfony/Oro knowledge. Focus on correctness and non-obvious pitfalls, not tutorials. |

## Eval Baseline (iteration 1)

- With-skill: 94.9% pass rate vs baseline 64.1% (+30.8pp improvement)
- Strongest: integration-mq (+83pp), frontend-theme (+43pp)
- Weakest: entity-creation (no delta — both missed ExtendEntityInterface)
- Full eval definitions: `evals/evals.json`

## Documentation Sources

All skill content verified against official OroCommerce v6.1 docs:

- https://doc.oroinc.com/backend/
- https://doc.oroinc.com/frontend/
- https://doc.oroinc.com/bundles/
- https://doc.oroinc.com/backend/extension/create-bundle/
- https://doc.oroinc.com/backend/entities/create-entities/
- https://github.com/oroinc/platform
- https://github.com/oroinc/orocommerce
