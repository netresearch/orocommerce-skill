# Workflow Patterns Reference

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

## Testing and Debugging

Use the OroWorkflow UI in the backend to visualize workflow transitions. For debugging:
- Check `workflow_item` and `workflow_step` tables in the database
- Use `WorkflowManager::getWorkflowItem()` to inspect current state
- Enable query logging to see how workflows filter entities

## WorkflowManager PHP API

```php
$workflowItem = $this->workflowManager->getWorkflowItem($entity);
if ($workflowItem?->getCurrentStep()?->getName() === 'approved') { ... }
```

## Additional Pitfalls

- **Form type instantiation**: Form types in transitions must be fully qualified class names and instantiable without constructor arguments (or with service injection via DI).
- **Query building**: Workflows don't filter entities by their step automatically. Use `WorkflowManager` to check step programmatically.
- **Cache invalidation**: After modifying `workflows.yml`, clear the cache:
  ```bash
  bin/console cache:clear
  bin/console oro:workflow:definitions:load
  ```
