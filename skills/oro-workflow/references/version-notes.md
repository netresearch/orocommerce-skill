# Workflow Version Notes — OroCommerce v6.1

## v6.1 Specifics

### Supported Features

- Full workflow/transition/step definition via YAML
- Workflow attributes with property mapping
- Transition forms for user input collection
- Condition and action expressions (see condition-expressions.md)
- Checkout workflow import and customization
- Operations (single-action user triggers)
- Event-triggered transitions
- Workflow scopes (default, frontend)
- ACL integration for transition authorization

### Key Changes from v5.1

- Workflow import syntax introduced (allows checkout customization)
- Event-triggered transitions stabilized
- Condition expression functions expanded
- PHP 8 attributes for Acl decorators (in SecurityBundle, not Workflow)
- Checkout workflow marked with `is_checkout_workflow: true` metadata

### Cache and Loading

Workflows are cached after first load. After modifying `workflows.yml`:

```bash
bin/console cache:clear
bin/console oro:workflow:definitions:load
```

The `oro:workflow:definitions:load` command validates and registers workflow definitions. Run it in production as part of deploy.

### Known Limitations

1. **No workflow inheritance**: Cannot extend a workflow without importing (import is override-based).
2. **No conditional step visibility**: All steps are always visible; hide via UI permissions instead.
3. **No async actions**: Actions execute synchronously during transition. Use events for async operations.
4. **No rollback**: If an action fails, the transition partially completes. Use transactions in services.

## Upcoming in v7.0 (Placeholder)

- Potential breaking changes in condition/action syntax
- Possible expansion of event-triggered capability
- Consider v6.1 features as baseline for v7.0 compatibility

When upgrading, review:
- Condition syntax for deprecated functions
- Checkout workflow metadata
- Custom workflow scopes
- Action expression compatibility
