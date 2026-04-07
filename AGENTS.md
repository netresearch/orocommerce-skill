<!-- Managed by agent: keep sections and order; edit content, not structure. -->

# AGENTS.md — OroCommerce Skills Plugin

> **Precedence:** The closest scoped AGENTS.md wins. CLAUDE.md governs user preferences; AGENTS.md governs agent behavior.

## Overview

8 Claude Code skills for OroCommerce v6.1 development, packaged as a plugin. Each skill provides domain-specific guidance for code generation, configuration, and best practices.

## Index of scoped AGENTS.md

| Scope | Path | Description |
|-------|------|-------------|
| oro-api | `skills/oro-api/` | REST API via api.yml, processors, JSON:API |
| oro-bundle | `skills/oro-bundle/` | Bundle scaffolding, DI, services, navigation |
| oro-datagrid | `skills/oro-datagrid/` | Datagrid YAML, columns, filters, actions |
| oro-entity | `skills/oro-entity/` | Entities, ownership, ExtendEntity, migrations |
| oro-frontend | `skills/oro-frontend/` | Themes, SCSS, Twig layouts, JS components |
| oro-integration | `skills/oro-integration/` | MQ, import/export, connectors, cron |
| oro-security | `skills/oro-security/` | ACL, permissions, access rules, field ACL |
| oro-workflow | `skills/oro-workflow/` | Workflows, transitions, checkout customization |

## Key Files

| File | Purpose |
|------|---------|
| `.claude-plugin/plugin.json` | Plugin manifest — name, version, skill paths |
| `composer.json` | Composer distribution metadata |
| `skills/evals/evals.json` | Eval definitions for all 8 skills |
| `docs/ARCHITECTURE.md` | Architecture, component map, directory tree |

## Commands

| Command | Purpose |
|---------|---------|
| `claude plugin validate` | Validate plugin.json and skill structure |
| `make verify-harness` | Verify harness consistency |

## Rules

- **SKILL.md**: YAML frontmatter (`name`, `description`, `version`), max 500 lines, overflow in `references/`
- **Code examples**: OroCommerce v6.1 only — PHP 8 attributes, `#[\Override]`, not annotations
- **Licensing**: Code = MIT, content = CC-BY-SA-4.0, entity = `Netresearch DTT GmbH`
- **Commits**: Conventional commits format
- **Skills are independent**: no cross-skill imports or dependencies

## PR Checklist

- [ ] SKILL.md files have valid YAML frontmatter
- [ ] plugin.json lists all skill directories
- [ ] No skill exceeds 500 lines
- [ ] Code examples use v6.1 patterns
- [ ] Evals cover changed skills
- [ ] version-notes.md updated if version-specific content changed

## References

- [Architecture](docs/ARCHITECTURE.md)
- [Active Plans](docs/exec-plans/active/)
