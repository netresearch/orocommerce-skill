---
name: oro-entity
description: "Use when creating new OroCommerce v6.1 Doctrine entities, extending existing Oro core entities (like Product, Order, Customer), writing schema migrations, configuring entity ownership (USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL), using ConfigField attributes, creating enum entities, or working with ExtendEntity traits. Relevant when the user mentions 'create entity', 'add field to Product', 'write migration', 'extend entity', 'custom field', or entity ownership questions in OroCommerce context."
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

Use `ExtendEntityInterface` + `ExtendEntityTrait` only when admin users need to add custom fields at runtime via Entity Management (System -> Entity Management). The trait provides magic `__get`/`__set`/`__isset`/`__call` for runtime-added fields. Standard entities that won't be extended at runtime don't need it.

## Ownership Decision Tree

- Static/reference data? -> **GLOBAL**
- Shared within org, not by department? -> **ORGANIZATION**
- Department/team owned? -> **BUSINESS_UNIT** (include both org + BU fields)
- Personal/assigned to user? -> **USER** (include both org + user fields)

**CRITICAL: USER and BUSINESS_UNIT ownership both require an `organization` field.** Missing it causes silent access control failures. See `references/ownership-types.md` for full config of all four types.

## Migration: Creating a Table

```php
namespace Acme\Bundle\DemoBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class CreateDocumentTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->createTable('acme_demo_document');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('organization_id', 'integer', ['notnull' => false]);
        $table->addColumn('owner_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }
}
```

Place migrations in `src/Acme/Bundle/DemoBundle/Migrations/Schema/`. Organize by version subdirectories (`v1_0/`, `v1_1/`).

## Key Pitfalls

1. **Missing organization field on USER/BUSINESS_UNIT ownership** — Access control fails silently
2. **Using old `@ORM\` annotations instead of `#[ORM\...]` attributes** — Doctrine won't recognize them in v6.1
3. **Enum codes over 21 characters** — Oro uses them to generate table names; exceeding the limit causes silent failures

## See Also

- `references/ownership-types.md` — Complete ownership type configurations and field requirements
- `references/entity-patterns.md` — Enum entities, ConfigField details, extending core entities, repositories, commands, additional pitfalls
- `references/v6.1.md` — v6.1 specifics, migration checklist, and common failures
- `references/v7.0.md` — v7.0 changes (placeholder)
