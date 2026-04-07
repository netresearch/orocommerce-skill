# Workflow Condition and Action Expressions — OroCommerce v6.1

This reference covers all built-in expression functions available in workflow conditions (`preconditions`, `postconditions`, `condition` on transitions) and actions in workflow definitions.

## Accessing Data

- `$attribute_name`: Workflow attribute (e.g., `$is_manager`, `$document_priority`)
- `$entity`: The entity the workflow is managing
- `$.now`: Current datetime
- `$.user`: Currently authenticated user
- `$entity.property`: Entity property access (uses Symfony PropertyAccess)

## Boolean Expressions (Conditions)

Used in `preconditions`, `postconditions`, `condition` on transitions.

### @and — Logical AND

All sub-expressions must be true.

```yaml
'@and':
    - '@eq': [$is_manager, true]
    - '@gte': [$priority, 3]
```

### @or — Logical OR

At least one sub-expression must be true.

```yaml
'@or':
    - '@eq': [$status, pending]
    - '@eq': [$status, draft]
```

### @not — Logical NOT

Inverts the result of a sub-expression.

```yaml
'@not':
    - '@empty': [$notes]
```

### @eq — Equal

```yaml
'@eq': [$status, approved]
'@eq': [$amount, 100]
```

Compares types strictly. `null == 0` is false; use `@null` for null checks.

### @not_eq — Not Equal

```yaml
'@not_eq': [$status, rejected]
```

### @gt — Greater Than

```yaml
'@gt': [$priority, 2]
```

### @gte — Greater Than or Equal

```yaml
'@gte': [$priority, 3]
```

### @lt — Less Than

```yaml
'@lt': [$priority, 5]
```

### @lte — Less Than or Equal

```yaml
'@lte': [$amount, 1000]
```

### @empty — Is Empty

True if value is empty (null, empty string, empty array).

```yaml
'@empty': [$notes]
```

### @not_empty — Is Not Empty

True if value is not empty.

```yaml
'@not_empty': [$approval_notes]
```

### @blank — Is Blank

Similar to `@empty` but treats whitespace-only strings as blank.

```yaml
'@blank': [$field]
```

### @null — Is Null

True if value is null. Stricter than `@empty`.

```yaml
'@null': [$deleted_at]
```

### @in — Value In List

True if first value is in the list.

```yaml
'@in': [$status, [approved, published, archived]]
```

### @contains — String or Array Contains

For strings: substring check. For arrays: element check.

```yaml
'@contains': [$notes, 'urgent']
'@contains': [$tags, admin]
```

## Action Expressions

Used under `actions:` to modify state or trigger side effects.

### @assign_value — Assign to Attribute

Set an attribute to a value (can be expression).

```yaml
- '@assign_value': [$approved_at, $.now]
- '@assign_value': [$approver, $.user]
- '@assign_value': [$status, approved]
```

Attributes must be defined in the workflow's `attributes:` section.

### @unset_value — Remove Attribute

Clear an attribute value (set to null).

```yaml
- '@unset_value': [$temporary_flag]
```

### @call_method — Call Object Method

Invoke a method on an entity or service.

```yaml
- '@call_method':
    object: $entity
    method: markApproved
    method_parameters: [$.user, $.now]
```

- `object`: Entity or attribute to call method on
- `method`: Method name (string)
- `method_parameters`: List of parameters (positional)

### @create_entity — Create and Store Entity

Instantiate and persist a new entity.

```yaml
- '@create_entity':
    class: Acme\Bundle\DemoBundle\Entity\DocumentApproval
    attribute: $.approval_record
    data:
        document: $entity
        approver: $.user
        approved_at: $.now
```

- `class`: Full class name of entity to create
- `attribute`: Workflow attribute to store instance in (optional)
- `data`: Mapping of entity properties to values

The entity is persisted immediately.

### @remove_entity — Delete Entity

Remove entity from database.

```yaml
- '@remove_entity':
    target: $entity
```

### @create_datetime — Create DateTime

Create a datetime attribute.

```yaml
- '@create_datetime':
    attribute: $.approval_date
    timezone: UTC
    datetime: 2025-01-15T10:30:00
```

### @format_string — Format String

Format using sprintf-style patterns.

```yaml
- '@assign_value':
    - $formatted_id
    - '@format_string':
        - 'DOC-%s-%d'
        - [$entity.id, $.timestamp]
```

### @trans — Translate String

Translate using Symfony translation catalog.

```yaml
- '@assign_value':
    - $message
    - '@trans':
        id: 'acme.document.approved_message'
        domain: 'messages'
```

### @fetch_entity — Fetch by ID/Condition

Retrieve entity from database.

```yaml
- '@fetch_entity':
    entity_name: Acme\Bundle\DemoBundle\Entity\Document
    where:
        id: $parent_document_id
    attribute: $.parent
```

### @fetch_entities — Fetch Multiple Entities

Retrieve collection of entities.

```yaml
- '@fetch_entities':
    entity_name: Acme\Bundle\DemoBundle\Entity\Document
    where:
        status: approved
    order_by:
        created_at: DESC
    attribute: $.related_docs
```

## Common Patterns

### Check If User Has Role

```yaml
'@and':
    - '@not_empty': [$.user]
    - '@contains': [$.user.roles, ROLE_ADMIN]
```

### Set Multiple Attributes Conditionally

```yaml
actions:
    - '@if':
        conditions:
            - '@eq': [$status, approved]
        actions:
            - '@assign_value': [$approved_at, $.now]
            - '@assign_value': [$approver, $.user]
```

### Create Related Record

```yaml
actions:
    - '@create_entity':
        class: Acme\Bundle\DemoBundle\Entity\Audit
        data:
            entity: $entity
            action: 'approved'
            user: $.user
            timestamp: $.now
```

### Branch Logic (Conditional Actions)

```yaml
- '@if':
    conditions:
        - '@eq': [$priority, high]
    actions:
        - '@call_method':
            object: '@acme_demo.notifier'
            method: notifyManager
            method_parameters: [$.user]
```

## Debugging Expressions

If expressions fail silently:

1. Check attribute names match definitions (case-sensitive)
2. Verify `@` and `$` prefix presence
3. Use simpler expressions first (`@eq` before complex `@and`)
4. Inspect workflow history in UI for execution logs
5. Check application logs for expression parsing errors

Expressions are parsed at workflow load time. Invalid YAML syntax will prevent the workflow from loading at all.
