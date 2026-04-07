# OroCommerce Claude Code Skills — Plugin Project

## What This Is

A set of 8 Claude Code skills that guide OroCommerce v6.1 development. The goal is to package these as a **Claude Code plugin** so any developer working on an Oro project can install them and get accurate, v6.1-specific code generation guidance.

## Project Owner

Paul Siedler (paul.siedler@netresearch.de) at Netresearch. Oro-experienced developer, full-stack + integration-heavy workflow.

## Current State

### Skills (ready for iteration)

All 8 skills live in `skills/` with this structure:
```
skills/
├── oro-bundle/         # 303 lines — Bundle scaffolding, DI, services, navigation, system config
├── oro-entity/         # 418 lines — Entities, ownership, ExtendEntity, migrations
├── oro-datagrid/       # 368 lines — Datagrid YAML config, columns, filters, actions
├── oro-api/            # 392 lines — REST API via api.yml/api_frontend.yml, processors
├── oro-workflow/       # 315 lines — Workflows, transitions, checkout customization
├── oro-frontend/       # 359 lines — Themes, SCSS, Twig layouts, Chaplin.js
├── oro-integration/    # 457 lines — Message queue, import/export, connectors, cron
└── oro-security/       # 398 lines — ACL, permissions, access rules, field ACL
```

Each skill has:
- `SKILL.md` — Main instructions with YAML frontmatter (name, description, version)
- `references/version-notes.md` — v6.1 specifics + v7.0 placeholder
- `references/*.md` — Domain-specific reference docs (ownership types, processor groups, etc.)

### Eval Results (iteration 1)

First eval run in `skills/orocommerce-skills-workspace/iteration-1/`:
- **With-skill: 94.9% pass rate vs baseline 64.1%** (+30.8pp improvement)
- Strongest wins: integration-mq (+83pp), frontend-theme (+43pp)  
- Weakest: entity-creation (no delta — both missed ExtendEntityInterface; skill needs strengthening)
- Eval viewer: `eval-review.html` in project root

### Research Docs (reference material)

The root folder contains extracted v6.1 documentation used to build the skills:
- `OroCommerce_v6.1_Configuration_Patterns.md` — Verified backend YAML/PHP patterns
- `OroCommerce_v6.1_Frontend_Documentation_Extract.md` — Verified frontend patterns
- `OROCOMMERCE_BACKEND_PATTERNS_RESEARCH.md` — Comprehensive backend reference
- `OROCOMMERCE_ANTI_PATTERNS.md` — 40+ documented anti-patterns
- `OROCOMMERCE_CODE_GENERATION_TEMPLATES.md` — Ready-to-use templates
- `SKILL_COMPARTMENTALIZATION_PROPOSAL.md` — Rationale for the 8-skill split

## What Needs To Happen Next

### 1. Strengthen weak skills

The `oro-entity` skill needs improvement — the eval showed it doesn't reliably produce ExtendEntityInterface implementations or proper migration files. Read the eval outputs in `skills/orocommerce-skills-workspace/iteration-1/entity-creation/` to see what went wrong.

### 2. Package as a Claude Code plugin

The skills need to be packaged into a plugin that can be installed via Claude Code's plugin system. Key decisions:
- Plugin name (e.g., `orocommerce` or `oro-dev`)
- Whether to include all 8 skills or group them differently
- Plugin metadata (description, author, version)
- Installation instructions

### 3. Version awareness for v7.0

Every skill has a `references/version-notes.md` with a v7.0 placeholder section. When v7.0 stabilizes:
- Update the version-notes with actual changes
- Adjust SKILL.md patterns where v7.0 breaks compatibility
- Consider a version selector or separate v7.0 variants

### 4. Run remaining evals

Only 6 of 16 test cases were run (one per skill for the 6 most critical). The full eval set is in `skills/evals/evals.json` with 2 prompts per skill. Run the remaining 10 for full coverage.

### 5. Description optimization

After skills are finalized, run the skill-creator's description optimization loop to improve triggering accuracy. This uses `claude -p` to test whether Claude correctly triggers each skill given various prompts.

## Key Design Decisions

- **v6.1 pinned**: All code examples use PHP 8 attributes (not annotations), `#[\Override]`, Oro's proprietary MQ (not Symfony Messenger), and v6.1 directory conventions
- **Audience**: Oro-experienced developers — skills assume Symfony/Oro knowledge, focus on correctness and non-obvious pitfalls rather than tutorials
- **Lean skills**: Each under 500 lines, with `references/` for overflow. Progressive disclosure: metadata triggers → SKILL.md loads → references read on demand
- **8-skill split**: Maps to developer intent ("I need to create a grid" → oro-datagrid). Not too few (kitchen sink) or too many (fragmented)

## Documentation Sources

All patterns verified against official OroCommerce v6.1 docs:
- https://doc.oroinc.com/backend/
- https://doc.oroinc.com/frontend/
- https://doc.oroinc.com/bundles/
- https://doc.oroinc.com/backend/extension/create-bundle/
- https://doc.oroinc.com/backend/entities/create-entities/
- https://github.com/oroinc/platform
- https://github.com/oroinc/orocommerce

## File Cleanup Note

The root folder has research docs that were useful during creation but shouldn't ship with the plugin. The plugin should only contain the `skills/` directory contents (minus `orocommerce-skills-workspace/` and `evals/`). The research docs can stay in the repo as reference.
