---
name: oro-security
description: "Use when configuring OroCommerce v6.1 ACL, permissions, and access control — setting up entity permissions (acls.yml), ownership types, Acl/AclAncestor PHP attributes on controllers, custom permissions, field-level ACL, access rules for query filtering, or debugging permission issues. Relevant when the user mentions 'ACL', 'permissions', 'access control', 'ownership', 'security', 'acls.yml', 'field ACL', 'access rules', or any OroCommerce authorization task."
---

# OroCommerce v6.1 Security & ACL Configuration

## Entity ACL Configuration

Place in `Resources/config/oro/acls.yml`:

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
```

Key structure: `type: entity`, `class` (FQCN), `permission` (VIEW, CREATE, EDIT, DELETE, ASSIGN, EXECUTE).

## PHP 8 Acl Attribute

Declare ACL requirements directly on controller methods:

```php
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
    }
}
```

Attributes are preferred over YAML bindings — they're colocated with the code they protect and type-safe.

## Ownership and Organization

Ownership determines who can access an entity. Four scopes: USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL.

**CRITICAL**: If ownership is USER or BUSINESS_UNIT, the entity MUST have an `organization` field. Missing it causes permission checks to fail silently.

Define in `Resources/config/oro/entity.yml`:

```yaml
entities:
    Acme\Bundle\DemoBundle\Entity\Document:
        ownership:
            owner_type: USER
            owner_field_name: owner
            organization_field_name: organization
```

## AclHelper: Query Filtering

ACL checks do NOT happen automatically on queries. You must explicitly apply filtering:

```php
class DocumentRepository
{
    public function findApprovedDocuments(User $user)
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', 'approved');

        $this->aclHelper->apply($qb);

        return $qb->getQuery()->getResult();
    }
}
```

Without `$aclHelper->apply($qb)`, the query returns all entities regardless of permissions.

For custom access rules (`AccessRuleInterface`), see [security-patterns.md](references/security-patterns.md).

## Key Pitfalls

1. **Query filtering is not automatic**: Must call `$aclHelper->apply($qb)` explicitly. Common mistake: fetching all entities and filtering in PHP rather than at DB level.

2. **AclAncestor skips object-level checks**: Use `AclAncestor` only for class-level permission verification (list pages). For specific objects, use full `Acl` attributes or `isGranted()`.

3. **Organization field is mandatory for USER/BUSINESS_UNIT ownership**: Omitting it causes permission checks to return false unexpectedly.

For `AclAncestor` details, custom permissions, field-level ACL, common patterns, debugging, and additional pitfalls, see [security-patterns.md](references/security-patterns.md).

## Version Notes

See [permission-matrix.md](references/permission-matrix.md) for the full permission/ownership matrix, [v6.1 notes](references/v6.1.md), and [v7.0 notes](references/v7.0.md).
