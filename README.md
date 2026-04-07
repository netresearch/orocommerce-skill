# OroCommerce Skills for Claude Code

A Claude Code plugin providing 8 domain-specific skills for OroCommerce v6.1 development. Each skill delivers accurate, version-pinned guidance for common development tasks — from entity creation and datagrid configuration to API design, workflow setup, and security hardening. Built for Oro-experienced developers who need correctness and awareness of non-obvious pitfalls rather than introductory tutorials.

## Skills

| Skill | Description |
|-------|-------------|
| **oro-api** | REST API configuration via `api.yml` / `api_frontend.yml`, processors, and resource policies |
| **oro-bundle** | Bundle scaffolding, dependency injection, service configuration, navigation, and system config |
| **oro-datagrid** | Datagrid YAML configuration — columns, filters, sorters, properties, and mass actions |
| **oro-entity** | Entity creation with ownership, `ExtendEntityInterface`, field config, and migrations |
| **oro-frontend** | Theme setup, SCSS, Twig layout updates, and Chaplin.js view integration |
| **oro-integration** | Message queue consumers, import/export, integration connectors, and cron jobs |
| **oro-security** | ACL annotations, permission configuration, access rules, and field-level ACL |
| **oro-workflow** | Workflow definitions, transitions, transition actions, and checkout customization |

## Installation

### Claude Code Marketplace

```bash
claude plugin install netresearch/orocommerce-skills
```

### Manual Installation

Clone this repository into your Claude Code plugins directory:

```bash
git clone https://github.com/netresearch/orocommerce-skills.git ~/.claude/plugins/orocommerce-skills
```

### Composer (for OroCommerce projects)

```bash
composer require netresearch/orocommerce-skills
```

## Requirements

- [Claude Code](https://docs.anthropic.com/en/docs/claude-code) CLI

## License

This project uses a split license:

- **Code** (plugin metadata, tooling): [MIT](LICENSE-MIT)
- **Skill content** (SKILL.md files, reference docs): [CC BY-SA 4.0](LICENSE-CC-BY-SA-4.0)

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Author

[Netresearch DTT GmbH](https://www.netresearch.de)
