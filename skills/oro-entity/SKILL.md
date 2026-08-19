---
name: oro-entity
description: "Use when creating OroCommerce v6.1 Doctrine entities, extending Oro core entities (Product, Order, Customer), writing schema migrations, configuring ownership (USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL), ConfigField attributes, enum entities, ExtendEntity traits, or removing/hard-deleting extend fields and attributes (soft-delete, RemoveFieldQuery, dropColumn). Triggers on 'create entity', 'add field to Product', 'write migration', 'extend entity', 'custom field', 'remove field', 'delete attribute'."
---

# OroCommerce v6.1 Entity Development

## Canonical Entity (PHP 8 Attributes)

This is the reference pattern combining `ExtendEntityInterface`, ownership, security, and `#[ConfigField]`:

```php
<?php
namespace Acme\Bundle\DemoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\ConfigField;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityInterface;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityTrait;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_document')]
#[Config(
    routeName: 'acme_demo_document_index',
    routeView: 'acme_demo_document_view',
    defaultValues: [
        'entity' => ['icon' => 'fa-file', 'label' => 'Document', 'plural_label' => 'Documents'],
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'user_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id',
        ],
        'security' => ['type' => 'ACL', 'permissions' => 'VIEW;CREATE;EDIT;DELETE', 'group_name' => ''],
    ]
)]
class Document implements ExtendEntityInterface
{
    use ExtendEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private string $title;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_owner_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    // Getters/setters...
}
```

## ExtendEntityInterface

Use `ExtendEntityInterface` + `ExtendEntityTrait` only when admins add fields at runtime via System → Entity Management; the trait supplies the magic `__get`/`__set`/`__isset`/`__call` for them. Entities not extended at runtime do not need it.

## Ownership Decision Tree

- Static/reference data? -> **GLOBAL**
- Shared within org, not by department? -> **ORGANIZATION**
- Department/team owned? -> **BUSINESS_UNIT** (include both org + BU fields)
- Personal/assigned to user? -> **USER** (include both org + user fields)

**CRITICAL: USER and BUSINESS_UNIT ownership both require an `organization` field.** Missing it causes silent access control failures. See `references/ownership-types.md` for full config of all four types.

## Migration: Creating a Table

Migrations live in `src/Acme/Bundle/DemoBundle/Migrations/Schema/`, one subdirectory per version (`v1_0/`, `v1_1/`). A `Migration` implementation builds the table in `up(Schema $schema, QueryBag $queries)` — worked example, including the ownership columns a USER/BUSINESS_UNIT entity needs, in `references/v6.1.md`.

## Key Pitfalls

1. **Missing organization field on USER/BUSINESS_UNIT ownership** — Access control fails silently
2. **Using old `@ORM\` annotations instead of `#[ORM\...]` attributes** — Doctrine won't recognize them in v6.1
3. **Enum codes over 21 characters** — Oro uses them to generate table names; exceeding the limit causes silent failures
4. **Expecting admin-UI field delete to remove the column** — it only soft-deletes (config `state = Deleted`, column kept), which blocks re-creating a field with the same name; see `references/removing-extend-fields.md`

## See Also

- `references/ownership-types.md` — all four ownership types and their field requirements
- `references/entity-patterns.md` — enum entities, ConfigField, extending core entities, repositories, commands
- `references/v6.1.md` — v6.1 specifics, the migration example, common failures
- `references/removing-extend-fields.md` — RemoveFieldQuery, attribute-family cleanup, POST_UP listeners, multi-host caveats
