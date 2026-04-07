# Permission and Ownership Matrix — OroCommerce v6.1

## Permission Types

Six permission types control access to entities and actions:

| Permission | Applies To | Use Case |
|-----------|-----------|----------|
| `VIEW` | Entity fields | Read access |
| `CREATE` | Entity class | Create new instances |
| `EDIT` | Entity fields | Modify values |
| `DELETE` | Entity instances | Remove from database |
| `ASSIGN` | Entity instances | Change owner/reassign |
| `EXECUTE` | Actions/Workflows | Trigger operations |

## Ownership Scopes

Ownership determines the set of users who can access an entity based on their role/organization membership:

| Ownership Type | Accessible To | Requires | Use Case |
|---|---|---|---|
| `GLOBAL` | All users | None | System-wide entities (Products, Categories) |
| `ORGANIZATION` | Users in owning organization | `organization` field | Multi-tenant organization entities |
| `BUSINESS_UNIT` | Users in owning business unit and parent units | `organization`, `owner` field | Department-level records |
| `USER` | The owning user + their team leads | `organization`, `owner` field | Personal/user-owned records |

## Permission Resolution Matrix

When a user requests permission on an entity, Symfony ACL + OroCommerce ownership rules determine the result:

### For GLOBAL Ownership

```
User Role has VIEW permission on Entity class?
├─ Yes → Grant VIEW access to all instances
└─ No  → Deny
```

Example: All users can view Products (unless explicitly denied by role).

### For ORGANIZATION Ownership

```
User Role has permission on Entity class?
├─ Yes
│  └─ Entity.organization == User.organization?
│     ├─ Yes → Grant
│     └─ No  → Deny
└─ No  → Deny
```

Example: User can VIEW Document only if Document belongs to their organization.

### For BUSINESS_UNIT Ownership

```
User Role has permission on Entity class?
├─ Yes
│  └─ Entity.owner.businessUnit in User.businessUnits or User.businessUnit.parent?
│     ├─ Yes → Grant
│     └─ No  → Deny
└─ No  → Deny
```

Example: User can VIEW Document if they're in the owner's business unit or a parent unit.

### For USER Ownership

```
User Role has permission on Entity class?
├─ Yes
│  └─ Entity.owner == User OR Entity.owner in User.subordinates?
│     ├─ Yes → Grant
│     └─ No  → Deny
└─ No  → Deny
```

Example: User can VIEW Document if they own it or are the owner's manager.

## Common Configurations

### Product (Global, Public Read)

```yaml
# entity.yml
Acme\Product\Entity\Product:
    ownership:
        owner_type: GLOBAL

# acls.yml
acme_product_view:
    type: entity
    class: Acme\Product\Entity\Product
    permission: VIEW
```

All authenticated users can view all products.

### Document (Organization-Scoped)

```yaml
# entity.yml
Acme\Document\Entity\Document:
    ownership:
        owner_type: ORGANIZATION
        organization_field_name: organization

# acls.yml
acme_document_view:
    type: entity
    class: Acme\Document\Entity\Document
    permission: VIEW
```

Users can only view documents in their organization.

### Order (Business Unit + User Owned)

```yaml
# entity.yml
Acme\Order\Entity\Order:
    ownership:
        owner_type: BUSINESS_UNIT
        owner_field_name: sales_manager
        organization_field_name: organization

# acls.yml
acme_order_edit:
    type: entity
    class: Acme\Order\Entity\Order
    permission: EDIT
```

Sales managers can edit orders in their business unit; higher-level managers can edit orders in subordinate units.

## Permission × Ownership Interaction

### Scenario: User with EDIT on Organization-Owned Document

```
User Role:  EDIT permission on Document class
Document:   organization = Acme Corp (User in Acme Corp)
Result:     Granted
```

### Scenario: User with DELETE on Organization-Owned Document

```
User Role:  DELETE permission on Document class
Document:   organization = Acme Corp (User NOT in Acme Corp)
Result:     Denied
```

### Scenario: Manager with ASSIGN on Business Unit Document

```
User Role:  ASSIGN permission on Document class, Manager of Business Unit B
Document:   owner.businessUnit = Business Unit A (sibling, not subordinate)
Result:     Denied (cannot assign across business units)
```

## Role-Based Access Control (RBAC) Setup

Roles define which permissions are granted to which users. In the UI, administrators create roles and assign permissions:

```
System > Users > Roles > Create Role

Name: Sales Manager
Permissions:
  - Entity: Document
    Permission: VIEW (Organization)
    ✓ Granted
  - Entity: Document
    Permission: EDIT (Organization)
    ✓ Granted
  - Entity: Document
    Permission: DELETE (Organization)
    ✗ Denied
```

This role grants VIEW and EDIT on all documents in the user's organization, but prevents deletion.

## Field-Level Permissions

Combine entity-level and field-level ACLs to restrict access to sensitive fields:

```yaml
acme_document_view:
    type: entity
    class: Acme\Document\Entity\Document
    permission: VIEW

acme_document_price_view:
    type: entity
    class: Acme\Document\Entity\Document
    permission: VIEW
    field_name: price
```

Resolution:
1. User must have `acme_document_view` (entity-level)
2. If accessing the `price` field, must also have `acme_document_price_view`

Example:
```twig
{% if attribute_is_granted('VIEW', document) %}
    <!-- Can view the document -->
    <p>Name: {{ document.name }}</p>

    {% if attribute_is_granted('VIEW', document, 'price') %}
        <!-- Can view the price field -->
        <p>Price: {{ document.price }}</p>
    {% endif %}
{% endif %}
```

## Troubleshooting Permission Issues

### Issue: User Can View But Not Edit

Check:
1. Entity ownership type matches user's organization/business unit
2. Role has EDIT permission granted in the UI
3. Custom access rules don't block EDIT
4. Field-level EDIT permissions (if defined)

### Issue: ACL Grants Permission But Query Returns Empty

Check:
1. `$aclHelper->apply($qb)` is called on the query builder
2. Entity has proper ownership field populated
3. User's organization/business unit matches entity

### Issue: Permission Matrix Shows Granted But isGranted() Returns False

Check:
1. Correct permission type (VIEW vs EDIT vs DELETE)
2. Correct object passed to `isGranted()`
3. User is authenticated (not Anonymous)
4. Custom access rules are not denying

## Advanced: Custom Access Rules

Access rules filter queries based on complex logic beyond ownership. Example: allow viewing documents created after a date:

```php
use Oro\Bundle\SecurityBundle\AccessRule\AccessRuleInterface;
use Oro\Bundle\SecurityBundle\AccessRule\Criteria;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Comparison;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Path;

class DocumentAccessRule implements AccessRuleInterface
{
    #[\Override]
    public function isApplicable(Criteria $criteria): bool
    {
        return true;
    }

    #[\Override]
    public function process(Criteria $criteria): void
    {
        $criteria->andExpression(
            new Comparison(new Path('owner'), Comparison::EQ, $currentUserId)
        );
    }
}
```

Register with the correct service tag:

```yaml
Acme\Bundle\DemoBundle\AccessRule\DocumentAccessRule:
    tags:
        - { name: oro_security.access_rule, type: ORM, entityClass: Acme\Bundle\DemoBundle\Entity\Document }
```

This rule is applied automatically to all Document queries.

Access rules and role-based permissions compose: both conditions must be satisfied.

```
User allowed by role? AND Document passes access rule?
├─ Yes, Yes → Grant
└─ Otherwise → Deny
```
