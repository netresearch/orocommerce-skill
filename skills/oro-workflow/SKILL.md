---
name: oro-workflow
description: "Use when creating OroCommerce v6.1 approval workflows, customizing checkout flow, defining workflow steps and transitions, configuring transition conditions and actions, setting up operations, or working with workflow scopes. Relevant when the user mentions 'workflow', 'checkout customization', 'approval process', 'state machine', 'transitions', 'operations.yml', 'workflows.yml', or any OroCommerce business process automation."
---

# OroCommerce v6.1 Workflow Configuration Skill

## File Locations

- **Workflow definitions**: `Resources/config/oro/workflows.yml`
- **Operations**: `Resources/config/oro/operations.yml`

## Basic Workflow Structure

Each workflow requires `label`, `entity`, `start_step`, `steps`, and `transitions`:

```yaml
workflows:
    document_approval:
        label: Document Approval
        entity: Acme\Bundle\DemoBundle\Entity\Document
        start_step: submitted

        steps:
            submitted:
                label: Submitted
                allowed_transitions: [approve, reject]
            approved:
                label: Approved
                allowed_transitions: [publish, reject]
            rejected:
                label: Rejected
                allowed_transitions: [resubmit]
            published:
                label: Published

        transitions:
            approve:
                label: Approve
                step_to: approved
                message: Document approved
            reject:
                label: Reject
                step_to: rejected
            resubmit:
                label: Resubmit
                step_to: submitted
            publish:
                label: Publish
                step_to: published
```

Steps without `allowed_transitions` are terminal states. Step names must be unique across all active workflows on the same entity.

## Transition Definitions with Conditions and Actions

```yaml
transition_definitions:
    approve_definition:
        preconditions:
            '@and':
                - '@eq': [$is_manager, true]
                - '@gte': [$document_priority, 3]
        actions:
            - '@assign_value': [$approved_at, $.now]
            - '@call_method':
                object: $entity
                method: markApproved

transitions:
    approve:
        label: Approve
        step_to: approved
        definition: approve_definition
```

Preconditions block the transition if false. Actions execute only on success. See `references/condition-expressions.md` for the full expression list.

## Checkout Workflow Customization

Customize checkout by importing and overriding `b2b_flow_checkout`:

```yaml
workflows:
    custom_checkout:
        import:
            - workflow: b2b_flow_checkout
        label: Custom Checkout
        metadata:
            is_checkout_workflow: true
```

**CRITICAL**: Preserve `is_checkout_workflow: true` in metadata. Without it, checkout breaks silently. Override individual steps or transitions by redeclaring them under the same key.

## Key Pitfalls

1. **Step/transition name uniqueness**: Names must be unique across ALL active workflows on the same entity type. Use prefixes: `document_pending`, `order_pending`.

2. **Import path correctness**: Reference the original workflow name exactly. Typos silently fail without error.

3. **Condition syntax**: Conditions use `@` prefix for functions and `$` prefix for attributes. Missing either causes parsing errors:
   ```yaml
   - '@eq': [$is_manager, true]  # Correct
   - 'eq': [$is_manager, true]   # Wrong — no @ prefix
   - '@eq': [is_manager, true]   # Wrong — missing $ prefix
   ```

## See Also

- `references/workflow-patterns.md` — Attributes, operations, event-triggered transitions, scopes, common patterns, testing/debugging, WorkflowManager API
- `references/condition-expressions.md` — Full expression reference
- [v6.1 notes](references/v6.1.md) | [v7.0 notes](references/v7.0.md)
