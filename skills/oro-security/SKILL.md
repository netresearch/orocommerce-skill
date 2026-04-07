---
name: oro-security
description: "Use when configuring OroCommerce v6.1 ACL, permissions, and access control — setting up entity permissions (acls.yml), ownership types, Acl/AclAncestor PHP attributes on controllers, custom permissions, field-level ACL, access rules for query filtering, or debugging permission issues. Relevant when the user mentions 'ACL', 'permissions', 'access control', 'ownership', 'security', 'acls.yml', 'field ACL', 'access rules', or any OroCommerce authorization task."
---

# OroCommerce v6.1 Security & ACL Configuration Skill

## File Locations

- **ACL definitions**: `Resources/config/oro/acls.yml`
- **Custom permissions**: `Resources/config/oro/permissions.yml`
- **Entity ownership**: Defined via `Resources/config/oro/entity.yml` (covered in oro-entity skill)

## Entity ACL Configuration

Entity ACLs define permissions on entity classes. Place in `Resources/config/oro/acls.yml`:

```yaml
acls:
    acme_demo_document_view:
        type: entity
        class: Acme\Bundle\DemoBundle\Entity\Document
        permission: VIEW
        description: View documents

    acme_demo_document_edit:
        type: entity
        class: Acme\Bundle\DemoBundle\Entity\Document
        permission: EDIT
        description: Edit documents

    acme_demo_document_delete:
        type: entity
        class: Acme\Bundle\DemoBundle\Entity\Document
        permission: DELETE
```

Key structure:
- `type: entity` marks this as entity-level ACL
- `class`: Fully qualified entity class path
- `permission`: One of VIEW, CREATE, EDIT, DELETE, ASSIGN, EXECUTE
- `bindings`: (optional) Controllers/methods to auto-protect

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

## Binding ACLs to Controllers

The `bindings` section auto-protects controller methods with ACL checks. The framework automatically verifies ACL before entering the action:

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

Bindings work by matching controller class + method name. If ACL fails, a 403 response is returned automatically.

**Note**: Bindings protect the controller but don't prevent access in services. Always check ACL explicitly in business logic.

## PHP 8 Acl Attribute

OroCommerce v6.1 supports PHP 8 attributes for declaring ACL requirements on controller methods. This is equivalent to bindings but more readable:

```php
<?php

namespace Acme\Bundle\DemoBundle\Controller;

use Oro\Bundle\SecurityBundle\Attribute\Acl;

class DocumentController
{
    #[Acl(
        id: 'acme_demo_document_view',
        type: 'entity',
        class: 'Acme\Bundle\DemoBundle\Entity\Document',
        permission: 'VIEW'
    )]
    public function viewAction(Document $document)
    {
        // ACL is checked before this method executes
        return $this->render('AcmeDemoBundle:Document:view.html.twig', [
            'document' => $document
        ]);
    }

    #[Acl(
        id: 'acme_demo_document_edit',
        type: 'entity',
        class: 'Acme\Bundle\DemoBundle\Entity\Document',
        permission: 'EDIT'
    )]
    public function editAction(Request $request, Document $document)
    {
        // ...
    }
}
```

**Why use attributes over bindings**: Attributes are colocated with the code they protect, making intent explicit. They're type-safe and avoid the string-matching fragility of bindings.

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

## Permission Types

Six permission types govern entity and action authorization:

| Permission | Meaning |
|-----------|---------|
| `VIEW` | Read entity/fields |
| `CREATE` | Instantiate new entity |
| `EDIT` | Modify entity/fields |
| `DELETE` | Remove from database |
| `ASSIGN` | Change ownership/assign to other users |
| `EXECUTE` | Execute custom operations (workflows, actions) |

Ownership types (USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL) further scope permissions. See `references/permission-matrix.md` for the full matrix.

## Ownership Types and Mandatory Organization Field

Ownership determines who can access an entity. Four scopes exist:

- **USER**: Only the owning user (requires `owner` field)
- **BUSINESS_UNIT**: Users in the owning business unit
- **ORGANIZATION**: All users in the owning organization
- **GLOBAL**: All users (unrestricted)

**CRITICAL**: If ownership is USER or BUSINESS_UNIT, the entity MUST have an `organization` field. OroCommerce uses organization to determine role/business unit membership. Missing it causes permission checks to fail silently.

Define ownership in `Resources/config/oro/entity.yml` (not in `acls.yml`):

```yaml
entities:
    Acme\Bundle\DemoBundle\Entity\Document:
        ownership:
            owner_type: USER
            owner_field_name: owner
            organization_field_name: organization
```

The `owner` field is typically `#[ORM\ManyToOne(targetEntity: User::class)]`. The `organization` field is `#[ORM\ManyToOne(targetEntity: Organization::class)]`.

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

## Access Rules and Query Filtering

**Important**: ACL checks do NOT happen automatically on queries. You must explicitly apply ACL filtering to query builders. This is a common pitfall.

Use the `AclHelper` service to apply ACL to queries:

```php
class DocumentRepository
{
    private $aclHelper;

    public function findApprovedDocuments(User $user)
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', 'approved');

        // Apply ACL filtering
        $this->aclHelper->apply($qb);

        return $qb->getQuery()->getResult();
    }
}
```

Without `$aclHelper->apply($qb)`, the query returns all entities regardless of permissions.

**Custom access rules**: Create custom logic for filtering by implementing `AccessRuleInterface`. The interface uses `Criteria` (not `QueryBuilder`) and expression objects for building conditions:

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

**Note**: The three-argument `isGranted($permission, $object, $field)` call is Oro's extended authorization checker (`Oro\Bundle\SecurityBundle\Authorization\AuthorizationChecker`), not standard Symfony. Standard Symfony `isGranted()` only accepts two arguments. This extension is available in controllers via `AbstractController::isGranted()` and via the `security.authorization_checker` service in Oro.

## Console Commands

Load and validate ACL definitions:

```bash
bin/console security:permission:configuration:load
```

This command parses `acls.yml` and `permissions.yml`, validates syntax, and registers permissions. Run after modifying security config.

Clear ACL cache (rarely needed):

```bash
bin/console cache:clear
```

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

## Key Pitfalls and Gotchas

1. **Query filtering is not automatic**: ACL checks don't happen on queries. Must call `$aclHelper->apply($qb)` explicitly. Common mistake: fetching all entities and filtering in PHP rather than at DB level.

2. **AclAncestor skips object-level checks**: Use `AclAncestor` only for class-level permission verification (e.g., list pages). For specific objects, use full `Acl` attributes or `isGranted()` checks.

3. **Organization field is mandatory for USER/BUSINESS_UNIT ownership**: Omitting it causes permission checks to return false unexpectedly. Always define `organization_field_name` in `entity.yml`.

4. **Bindings don't protect services**: Bindings only protect controller entry points. Business logic in services must check ACL explicitly. Don't rely on controller protection alone.

5. **ACL caching**: After modifying `acls.yml`, run `security:permission:configuration:load`. New permissions may not be visible in the UI until cache is cleared.

6. **Attribute class paths must be exact**: The class path in `#[Acl(..., class: '...')]` must match exactly (case-sensitive, full namespace).

7. **Custom permissions require group assignment**: Permissions without groups won't appear in the permissions matrix. Always specify `group_names: [default]` or custom group.

## Testing and Debugging

Check permissions in the backend UI at **System > Users > Roles**. Filter by entity/permission to verify ACL configuration.

For debugging:

```php
// Check if user has permission
$isGranted = $this->isGranted('VIEW', $entity);
// Returns true/false

// With field-level
$isGranted = $this->isGranted('VIEW', $entity, 'price');

// Debug why denied
if (!$isGranted) {
    // Check ownership
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

See `references/permission-matrix.md` for complete permission/ownership interaction matrix, [v6.1 notes](references/v6.1.md) for version-specific details, and [v7.0 notes](references/v7.0.md) for upcoming changes.
