# Architecture

## System Overview

This plugin provides 8 domain-specific skills for OroCommerce v6.1 development, packaged as a Claude Code plugin. Each skill targets a distinct developer intent (e.g., "I need to create a datagrid" triggers `oro-datagrid`) and delivers version-pinned code generation guidance, configuration patterns, and anti-pattern warnings.

## Component Map

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

## Dependency Rules

- **Skills are independent**: No skill imports from or depends on another skill.
- **References are per-skill**: Each skill's `references/` directory contains only content relevant to that skill.
- **Progressive disclosure**: Plugin metadata triggers skill selection. `SKILL.md` loads on activation. `references/*.md` files are read on demand for deeper detail.

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| **v6.1 pinned** | All examples use PHP 8 attributes (not annotations), `#[\Override]`, Oro's proprietary MQ (not Symfony Messenger), and v6.1 directory conventions. v7.0 placeholders exist in `references/version-notes.md`. |
| **8-skill split** | Maps to developer intent — not too few (kitchen sink) or too many (fragmented). Each skill corresponds to a recognizable development task. |
| **Lean skills (<500 lines)** | Keeps token usage low on activation. Detailed reference material lives in `references/` and is loaded only when needed. |
| **Split licensing** | Code/tooling under MIT for maximum reuse; skill content under CC BY-SA 4.0 to ensure attribution and share-alike for knowledge artifacts. |
| **Audience: Oro-experienced devs** | Skills assume Symfony/Oro knowledge. Focus on correctness and non-obvious pitfalls, not tutorials. |

## Eval Baseline (iteration 1)

- With-skill: 94.9% pass rate vs baseline 64.1% (+30.8pp improvement)
- Strongest: integration-mq (+83pp), frontend-theme (+43pp)
- Weakest: entity-creation (no delta — both missed ExtendEntityInterface)
- Full eval definitions: `skills/evals/evals.json`

## Documentation Sources

All skill content verified against official OroCommerce v6.1 docs:

- https://doc.oroinc.com/backend/
- https://doc.oroinc.com/frontend/
- https://doc.oroinc.com/bundles/
- https://doc.oroinc.com/backend/extension/create-bundle/
- https://doc.oroinc.com/backend/entities/create-entities/
- https://github.com/oroinc/platform
- https://github.com/oroinc/orocommerce
