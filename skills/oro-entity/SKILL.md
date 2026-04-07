---
name: oro-entity
description: "OroCommerce v6.1 entity creation, extension, and migration patterns. Use this skill when creating new Doctrine entities, extending existing Oro core entities (like Product, Order, Customer), writing schema migrations, configuring entity ownership (USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL), using ConfigField attributes, creating enum entities, or working with ExtendEntity traits. Triggers for any 'create entity', 'add field to Product', 'write migration', 'extend entity', 'custom field', or entity ownership questions in OroCommerce context."
---

# OroCommerce v6.1 Entity Development

## Entity Creation with PHP 8 Attributes

Create entities using PHP 8 attributes (not docblock annotations). Attributes are parsed at runtime in v6.1:

```php
<?php
namespace Acme\Bundle\DemoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\ConfigField;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_document')]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'owner_id',
        ],
    ]
)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(
        name: 'subject',
        type: 'string',
        length: 255,
        nullable: false
    )]
    private $subject;

    #[ORM\Column(
        name: 'description',
        type: 'text',
        nullable: true
    )]
    private $description;

    #[ORM\ManyToOne(targetEntity: Priority::class)]
    #[ORM\JoinColumn(
        name: 'priority_id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private $priority;

    #[ORM\CreatedAt]
    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\UpdatedAt]
    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    // Getters and setters...
}
```

**Why attributes matter:** They're parsed at runtime, enabling IDE autocomplete and type checking. Unlike annotations, they're not optional — Doctrine requires them.

## Ownership Configuration

The `#[Config]` attribute defines entity ownership. This determines access control and multi-tenancy behavior. Four types exist:

### GLOBAL
System-wide settings, no ownership:
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'GLOBAL',
        ],
    ]
)]
```
Use for: System configuration, global settings, reference data.
**No organization or owner field required.**

### ORGANIZATION
Multi-organization entities, scoped to an organization:
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'ORGANIZATION',
            'owner_field_name' => 'organization',
            'owner_column_name' => 'organization_id',
        ],
    ]
)]
```
Use for: Shared resources across departments within an organization.
**Organization field is MANDATORY.** Extend `OrganizationAwareTrait` for lifecycle hooks.

### BUSINESS_UNIT
Department/team scoped; implies multi-organization:
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'BUSINESS_UNIT',
            'owner_field_name' => 'businessUnit',
            'owner_column_name' => 'business_unit_id',
        ],
    ]
)]
```
Use for: Department-level resources (sales team, support queue).
**CRITICAL: Both `organization` and `businessUnit` fields are MANDATORY.** The `businessUnit` implies organization context.

### USER
Personal/individual ownership:
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'owner_id',
        ],
    ]
)]
```
Use for: Personal records (tasks, notes, assigned work).
**CRITICAL: Both `organization` and `owner` fields are MANDATORY.** User ownership always requires organization context.

**Decision tree:**
- Static/reference data? → GLOBAL
- Shared within org, not by department? → ORGANIZATION
- Department/team owned? → BUSINESS_UNIT (include both org + BU fields)
- Personal/assigned to user? → USER (include both org + user fields)

## Entity with Ownership Fields (USER type example)

```php
#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_task')]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'owner_id',
        ],
    ]
)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(name: 'title', type: 'string')]
    private $title;

    // MANDATORY: Organization field
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private $organization;

    // MANDATORY: Owner field
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false)]
    private $owner;
}
```

Missing organization or owner fields will cause access control failures.

## ExtendEntity Pattern

Use `ExtendEntityInterface` and `ExtendEntityTrait` for entities that support custom fields in the admin UI:

```php
<?php
namespace Acme\Bundle\DemoBundle\Entity;

use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityInterface;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_flexible')]
class FlexibleDocument implements ExtendEntityInterface
{
    use ExtendEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(name: 'name', type: 'string')]
    private $name;
}
```

This enables admins to add custom fields at runtime via System → Entity Management.

## ConfigField Attribute

Use `#[ConfigField]` to customize field behavior in the UI:

```php
#[ORM\Column(name: 'email', type: 'string', length: 255)]
#[ConfigField(
    defaultValues: [
        'dataaudit' => [
            'auditable' => true,
        ],
        'importexport' => [
            'order' => 10,
            'identity' => true,
        ],
    ]
)]
private $email;
```

Common options:
- `auditable: true` → Track changes in audit log
- `identity: true` → Use for import/export identity matching
- `order: N` → Column order in import/export

## Doctrine Migrations

Place migrations in `src/Acme/Bundle/DemoBundle/Migrations/Schema/`.

### Creating a New Table

```php
<?php
namespace Acme\Bundle\DemoBundle\Migrations\Schema;

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
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('organization_id', 'integer', ['notnull' => false]);
        $table->addColumn('owner_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['organization_id'], 'idx_document_org');
        $table->addIndex(['owner_id'], 'idx_document_owner');
    }
}
```

### Extending Core Entities

Extend core entities (Product, Order, Customer) using the `ExtendExtensionAwareInterface`:

```php
<?php
namespace Acme\Bundle\DemoBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionInterface;
use Oro\Bundle\EntityExtendBundle\Entity\Repository\EnumValueRepository;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddCustomFieldToProduct implements Migration, ExtendExtensionAwareInterface
{
    private ExtendExtensionInterface $extendExtension;

    public function setExtendExtension(ExtendExtensionInterface $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->extendExtension->addColumn(
            $schema,
            'oro_product',
            'custom_string_field',
            'string',
            [
                'extend' => [
                    'is_extend' => true,
                    'owner' => 'custom',  // ExtendScope::OWNER_CUSTOM
                ],
                'datagrid' => [
                    'is_visible' => true,
                ],
            ]
        );

        $this->extendExtension->addColumn(
            $schema,
            'oro_product',
            'custom_option_field',
            'string',
            [
                'extend' => [
                    'is_extend' => true,
                    'owner' => 'custom',
                    'target_entity' => 'Oro\Bundle\EntityExtendBundle\Entity\EnumValue',
                    'target_field' => 'id',
                ],
            ]
        );
    }
}
```

The `addColumn()` method handles the `oro_*_table` naming convention automatically.

### Migration Versioning

Organize migrations by version:

```
Migrations/Schema/
├── v1_0/
│   ├── CreateDocumentTable.php
│   └── AddIndexes.php
├── v1_1/
│   ├── AddCustomFieldToDocument.php
├── v1_2/
│   └── ...
```

Version subdirectories help track schema evolution. Use consistent naming: `CreateTableName.php` or `AddFieldToTable.php`.

## Entity Repository

Create a repository class for complex queries:

```php
<?php
namespace Acme\Bundle\DemoBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Acme\Bundle\DemoBundle\Entity\Document;

class DocumentRepository extends EntityRepository
{
    public function findByOrganization($organizationId)
    {
        return $this->createQueryBuilder('d')
            ->where('d.organization = :org')
            ->setParameter('org', $organizationId)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

Attach to entity via `#[ORM\Entity(repositoryClass: DocumentRepository::class)]`.

## Essential Commands

After entity/migration changes, run:

```bash
# Clear extended entity cache (required for ConfigField/Config attribute changes)
php bin/console oro:entity-extend:cache:clear

# Update database schema
php bin/console oro:entity-extend:update-schema

# Run migrations
php bin/console doctrine:migrations:migrate

# Verify entities loaded correctly
php bin/console doctrine:mapping:info
```

**Why this order matters:** Extend cache must clear before schema updates; migrations apply actual DDL.

## Common Pitfalls

1. **Missing organization field on USER/BUSINESS_UNIT ownership** — Access control fails silently
2. **Using old `@ORM\` annotations instead of `#[ORM\...]` attributes** — Doctrine won't recognize them
3. **Forgetting `#[\Override]` on repository query methods** — Less critical but improves IDE support
4. **Creating fields with nullable=true when ownership requires them** — Database constraints fail on insert
5. **Not running `oro:entity-extend:cache:clear`** — ConfigField changes won't appear in the UI

## See Also

- `references/v6.1.md` — v6.1 specifics, migration checklist, and common failures
- `references/v7.0.md` — v7.0 changes (placeholder)
