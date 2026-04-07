---
name: oro-workflow
description: "OroCommerce v6.1 workflow, operation, and action configuration. Use this skill when creating approval workflows, customizing checkout flow, defining workflow steps and transitions, configuring transition conditions and actions, setting up operations, or working with workflow scopes. Triggers for 'workflow', 'checkout customization', 'approval process', 'state machine', 'transitions', 'operations.yml', 'workflows.yml', or any OroCommerce business process automation."
---

# OroCommerce v6.1 Workflow Configuration Skill

## File Locations

- **Workflow definitions**: `Resources/config/oro/workflows.yml`
- **Operations**: `Resources/config/oro/operations.yml`
- **Transition definitions**: Usually inline in workflows.yml or separated

## Basic Workflow Structure

Workflows are root-level mappings under `workflows:` in `workflows.yml`. Each workflow requires:
- `label`: User-facing workflow name
- `entity`: Full class path to the entity this workflow manages
- `start_step`: Initial step when workflow initializes
- `steps`: Mapping of step name to step definition
- `transitions`: Mapping of transition name to transition definition

Example structure:

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
                ui_color: green
            reject:
                label: Reject
                step_to: rejected
                ui_color: red
            resubmit:
                label: Resubmit
                step_to: submitted
            publish:
                label: Publish
                step_to: published
```

Key design points:
- Steps without `allowed_transitions` are terminal states (no further transitions possible)
- Step names must be unique across all active workflows on the same entity (critical pitfall)
- `message` appears in workflow history; `ui_color` affects button appearance in UI

## Workflow Attributes (Variables)

Attributes persist across steps and enable dynamic data storage during workflow execution. Define under `attributes:` at workflow root:

```yaml
workflows:
    document_approval:
        attributes:
            is_manager:
                label: Is Manager
                type: bool
                property_path: entity.isManager
            document_priority:
                label: Priority
                type: integer
                property_path: entity.priority
            approved_at:
                label: Approved At
                type: datetime
            approval_notes:
                label: Approval Notes
                type: string
```

Attributes with `property_path` map to entity properties. Others are calculated or collected via forms. Attributes are referenced in conditions with `$` prefix: `$is_manager`, `$document_priority`.

## Transition Definitions with Conditions and Actions

Transitions control step changes and can include preconditions and postconditions with actions:

```yaml
workflows:
    document_approval:
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

Preconditions block the transition if false. Actions execute only if the transition succeeds. See `references/condition-expressions.md` for full expression list.

## Transition Forms

Collect user input during transitions by adding a form to the transition. The form data becomes available as attributes:

```yaml
transitions:
    reject:
        label: Reject
        step_to: rejected
        form_type: Acme\Bundle\DemoBundle\Form\RejectDocumentType
        form_options:
            data_class: ~
```

The form type should collect fields that map to attributes defined at workflow root. OroCommerce automatically populates attributes from form submission.

## Checkout Workflow Customization

The base checkout workflow is `b2b_flow_checkout` (or `default` for B2C). Customize by importing and overriding:

```yaml
workflows:
    custom_checkout:
        import:
            - workflow: b2b_flow_checkout
        label: Custom Checkout
        metadata:
            is_checkout_workflow: true
```

**CRITICAL**: Preserve `is_checkout_workflow: true` in metadata. Without it, the workflow will not be recognized as a checkout workflow and checkout may break.

To override a step:

```yaml
steps:
    billing_address:
        label: Billing Address (Custom)
        allowed_transitions: [shipping_address, order_review]
```

To override a transition, redeclare it:

```yaml
transitions:
    billing_address:
        label: Next Step
        step_to: shipping_address
        form_type: Acme\Bundle\DemoBundle\Form\BillingAddressType
```

## Operations (operations.yml)

Operations define single-action user-triggered buttons or menu items. Place in `Resources/config/oro/operations.yml`:

```yaml
operations:
    document_bulk_export:
        label: Export Documents
        entity: Acme\Bundle\DemoBundle\Entity\Document
        button_options:
            icon: download
        actions:
            - '@call_method':
                object: '@acme_demo.document.exporter'
                method: export
                method_parameters: [$entity]
```

Operations are useful for one-off actions that don't fit into a workflow. They respect ACL permissions automatically.

## Workflow Scopes and Activation

Scope determines when a workflow is active. Scopes include `default`, `frontend`, and custom. A workflow is active on an entity if:
1. Its entity matches
2. Its scope matches the current context
3. No other active workflow exists on the same entity

```yaml
workflows:
    document_approval:
        scopes:
            - default  # Backend workflow

    customer_registration:
        scopes:
            - frontend  # Storefront workflow
```

Only one workflow per scope per entity can be active. The last loaded workflow wins; order workflows via bundle dependencies.

## Event-Triggered Transitions

Oro does not support an inline `trigger: event` key in workflow YAML. To trigger transitions programmatically based on events, use a Symfony event listener or subscriber that calls `WorkflowManager::transit()`:

```php
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AutoApproveSubscriber implements EventSubscriberInterface
{
    public function __construct(private WorkflowManager $workflowManager) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return ['oro.workflow.transition.post_transition' => 'onPostTransition'];
    }

    public function onPostTransition(object $event): void
    {
        $entity = $event->getWorkflowItem()->getEntity();
        if ($entity->getPriority() <= 1) {
            $workflowItem = $this->workflowManager->getWorkflowItem($entity);
            $this->workflowManager->transit($workflowItem, 'auto_approve_low_priority');
        }
    }
}
```

Register the subscriber as a tagged service (`kernel.event_subscriber`).

## Common Patterns

**Conditional transitions**: Use `@eq`, `@and`, `@or` to gate transitions:

```yaml
transitions:
    ship_order:
        label: Ship
        step_to: shipped
        condition:
            '@and':
                - '@eq': [$payment_received, true]
                - '@not_empty': [$tracking_number]
```

**Entity creation**: Actions can create related entities:

```yaml
actions:
    - '@create_entity':
        class: Acme\Bundle\DemoBundle\Entity\DocumentApproval
        attribute: $.approval_record
        data:
            document: $entity
            approver: $.user
            approved_at: $.now
```

**PHP method calls**: Transition actions can invoke entity methods:

```yaml
actions:
    - '@call_method':
        object: $entity
        method: setApprovedAt
        method_parameters: [$.now]
```

## Key Pitfalls and Gotchas

1. **Step/transition name uniqueness**: Names must be unique across ALL active workflows on the same entity type. If two workflows define a step named `pending`, they will conflict. Use prefixes: `document_pending`, `order_pending`.

2. **Import path correctness**: When importing a workflow, reference the original workflow name exactly:
   ```yaml
   import:
       - workflow: b2b_flow_checkout  # Must match the exact workflow name
   ```
   Typos silently fail without error.

3. **Checkout metadata preservation**: Forgetting `is_checkout_workflow: true` will cause checkout breaks silently. Always verify it in custom checkout workflows.

4. **Condition syntax**: Conditions use `@` prefix for functions and `$` prefix for attributes. Missing either will cause parsing errors:
   ```yaml
   - '@eq': [$is_manager, true]  # Correct
   - 'eq': [$is_manager, true]   # Wrong — no @ prefix
   - '@eq': [is_manager, true]   # Wrong — missing $ prefix
   ```

5. **Form type instantiation**: Form types in transitions must be fully qualified class names and instantiable without constructor arguments (or with service injection via DI).

6. **Query building**: Workflows don't filter entities by their step automatically. Use `WorkflowManager` to check step programmatically:
   ```php
   $workflowItem = $this->workflowManager->getWorkflowItem($entity);
   if ($workflowItem?->getCurrentStep()?->getName() === 'approved') { ... }
   ```

7. **Cache invalidation**: After modifying `workflows.yml`, clear the cache:
   ```bash
   bin/console cache:clear
   bin/console oro:workflow:definitions:load
   ```

## Testing and Debugging

Use the OroWorkflow UI in the backend to visualize workflow transitions. For debugging:
- Check `workflow_item` and `workflow_step` tables in the database
- Use `WorkflowManager::getWorkflowItem()` to inspect current state
- Enable query logging to see how workflows filter entities

See `references/condition-expressions.md` for complete expression reference, [v6.1 notes](references/v6.1.md) for version-specific details, and [v7.0 notes](references/v7.0.md) for upcoming changes.
