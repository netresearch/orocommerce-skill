# Security Patterns Reference

## AclAncestor for Reuse

Reuse existing ACL definitions with `AclAncestor`. This checks class-level permissions only:

```php
#[\Oro\Bundle\SecurityBundle\Attribute\AclAncestor(
    id: 'acme_demo_document_view'
)]
public function listAction()
{
    // Checks acme_demo_document_view permission on Document class
    // Does NOT check object-level permissions on individual documents
    return $this->render('list.html.twig');
}
```

**Critical limitation**: `AclAncestor` does NOT check object-level permissions. It's suitable for list/index pages where you want to verify the user can view the entity type in general. For operations on specific objects, use full `Acl` attributes or explicit checks.

## Action-Type ACLs

Action ACLs define non-entity permissions (for arbitrary operations):

```yaml
acls:
    acme_demo_bulk_export:
        type: action
        label: Bulk Export Documents
        description: Export documents in bulk
```

Action ACLs don't reference a class and are checked manually in code:

```php
if (!$this->isGranted('acme_demo_bulk_export')) {
    throw $this->createAccessDeniedException();
}
```

## Custom Permissions

Define application-specific permissions in `Resources/config/oro/permissions.yml`:

```yaml
permissions:
    DOCUMENT_BULK_EXPORT:
        label: oro.acme.document.permissions.bulk_export.label
        description: oro.acme.document.permissions.bulk_export.description
        apply_to_entities:
            - Acme\Bundle\DemoBundle\Entity\Document
        group_names: [default]

    DOCUMENT_ARCHIVE:
        label: oro.acme.document.permissions.archive.label
        apply_to_entities:
            - Acme\Bundle\DemoBundle\Entity\Document
```

Custom permissions are managed in the UI and checked with `isGranted()`:

```php
if (!$this->isGranted('DOCUMENT_BULK_EXPORT')) {
    throw $this->createAccessDeniedException();
}
```

Labels should be translation keys; use `oro.acme.*` namespace for consistency.

**Important**: Permissions without groups won't appear in the permissions matrix. Always specify `group_names: [default]` or a custom group.

## AccessRuleInterface Full Example

Create custom query filtering logic by implementing `AccessRuleInterface`. The interface uses `Criteria` (not `QueryBuilder`) and expression objects:

```php
namespace Acme\Bundle\DemoBundle\AccessRule;

use Oro\Bundle\SecurityBundle\AccessRule\AccessRuleInterface;
use Oro\Bundle\SecurityBundle\AccessRule\Criteria;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Comparison;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Path;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

class DocumentAccessRule implements AccessRuleInterface
{
    public function __construct(
        private readonly TokenAccessorInterface $tokenAccessor
    ) {}

    #[\Override]
    public function isApplicable(Criteria $criteria): bool
    {
        return true; // Tag options handle entity filtering; use for complex logic only
    }

    #[\Override]
    public function process(Criteria $criteria): void
    {
        $criteria->andExpression(
            new Comparison(
                new Path('owner'),
                Comparison::EQ,
                $this->tokenAccessor->getUserId()
            )
        );
    }
}
```

Register in `services.yml` with entity class and type specified in the tag:

```yaml
Acme\Bundle\DemoBundle\AccessRule\DocumentAccessRule:
    tags:
        - { name: oro_security.access_rule, type: ORM, entityClass: Acme\Bundle\DemoBundle\Entity\Document }
```

The `entityClass` tag option filters which entities the rule applies to. The `type` option specifies the query type (typically `ORM`). The `isApplicable()` method provides additional runtime control beyond tag filtering.

## Field-Level ACL

Restrict access to specific entity fields. Define in `acls.yml`:

```yaml
acls:
    acme_demo_document_price:
        type: entity
        class: Acme\Bundle\DemoBundle\Entity\Document
        permission: VIEW
        field_name: price
```

This permission controls visibility of the `price` field specifically. The entity-level VIEW permission must also be granted to view any fields.

Use in Twig with `attribute_is_granted()`:

```twig
{% if attribute_is_granted('VIEW', entity, 'price') %}
    <p>Price: {{ entity.price }}</p>
{% endif %}
```

In PHP, check with `isGranted()`:

```php
if ($this->isGranted('VIEW', $entity, 'price')) {
    $price = $entity->getPrice();
}
```

**Note**: The three-argument `isGranted($permission, $object, $field)` call is Oro's extended authorization checker (`Oro\Bundle\SecurityBundle\Authorization\AuthorizationChecker`), not standard Symfony. Standard Symfony `isGranted()` only accepts two arguments.

## Binding ACLs to Controllers (YAML approach)

The `bindings` section in `acls.yml` auto-protects controller methods:

```yaml
acls:
    acme_demo_document_view:
        type: entity
        class: Acme\Bundle\DemoBundle\Entity\Document
        permission: VIEW
        bindings:
            - class: Acme\Bundle\DemoBundle\Controller\DocumentController
              method: viewAction
```

Bindings work by matching controller class + method name. If ACL fails, a 403 response is returned automatically. **Note**: Bindings protect the controller but don't prevent access in services.

## Common Patterns

### Protect a Service Method

```php
public function approveDocument(Document $document): void
{
    if (!$this->isGranted('EDIT', $document)) {
        throw $this->createAccessDeniedException();
    }
    // ... approval logic
}
```

### Check Ownership

```php
public function canModify(Document $document): bool
{
    return $document->getOwner() === $this->getUser();
}
```

### Fetch Only Accessible Entities

```php
$qb = $repository->createQueryBuilder('d');
$this->aclHelper->apply($qb, 'VIEW');
$documents = $qb->getQuery()->getResult();
```

### Check Permission in Twig

```twig
{% if is_granted('EDIT', entity) %}
    <a href="{{ path('edit_route') }}">Edit</a>
{% endif %}
```

## Testing and Debugging

Check permissions in the backend UI at **System > Users > Roles**. Filter by entity/permission to verify ACL configuration.

For debugging:

```php
// Check if user has permission
$isGranted = $this->isGranted('VIEW', $entity);

// With field-level
$isGranted = $this->isGranted('VIEW', $entity, 'price');

// Debug why denied — check ownership
if (!$isGranted) {
    dump($entity->getOwner());
    dump($this->getUser());
}
```

Enable query logging to see ACL-filtered queries:

```yaml
doctrine:
    dbal:
        logging: true
```

## Console Commands

Load and validate ACL definitions:

```bash
bin/console security:permission:configuration:load
```

This command parses `acls.yml` and `permissions.yml`, validates syntax, and registers permissions. Run after modifying security config.

## Additional Pitfalls

1. **Bindings don't protect services**: Bindings only protect controller entry points. Business logic in services must check ACL explicitly.

2. **ACL caching**: After modifying `acls.yml`, run `security:permission:configuration:load`. New permissions may not be visible in the UI until cache is cleared.

3. **Attribute class paths must be exact**: The class path in `#[Acl(..., class: '...')]` must match exactly (case-sensitive, full namespace).

4. **Custom permissions require group assignment**: Permissions without groups won't appear in the permissions matrix. Always specify `group_names: [default]` or custom group.
