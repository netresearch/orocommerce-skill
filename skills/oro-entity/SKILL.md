---
name: oro-entity
description: "Use when creating new OroCommerce v6.1 Doctrine entities, extending existing Oro core entities (like Product, Order, Customer), writing schema migrations, configuring entity ownership (USER, BUSINESS_UNIT, ORGANIZATION, GLOBAL), using ConfigField attributes, creating enum entities, or working with ExtendEntity traits. Relevant when the user mentions 'create entity', 'add field to Product', 'write migration', 'extend entity', 'custom field', or entity ownership questions in OroCommerce context."
---

# OroCommerce v6.1 Entity Development

## Entity Creation with PHP 8 Attributes

Create entities using PHP 8 attributes (not docblock annotations). Attributes are parsed at runtime in v6.1.

The following is the canonical pattern for a new OroCommerce entity. It combines `ExtendEntityInterface` (for runtime custom fields), `#[Config]` ownership, security configuration, and the mandatory ownership fields:

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
        'entity' => [
            'icon' => 'fa-file',
            'label' => 'Document',
            'plural_label' => 'Documents',
        ],
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'user_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id',
        ],
        'security' => [
            'type' => 'ACL',
            'permissions' => 'VIEW;CREATE;EDIT;DELETE',
            'group_name' => '',
        ],
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

    // MANDATORY for USER ownership: timestamp fields use standard Doctrine columns
    // (Oro uses lifecycle callbacks or Gedmo timestampable — NOT #[ORM\CreatedAt] or #[ORM\UpdatedAt])
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    // MANDATORY for USER ownership: owner field
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_owner_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $owner = null;

    // MANDATORY for USER ownership: organization field
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    // Getters/setters...
}
```

**Why attributes matter:** They're parsed at runtime, enabling IDE autocomplete and type checking. Unlike annotations, they're not optional — Doctrine requires them.

Use `ExtendEntityInterface` only when admin users need to add custom fields at runtime via the entity management UI (System -> Entity Management). Standard entities that won't be extended at runtime don't need it.

## Ownership Configuration

The `#[Config]` attribute defines entity ownership. This determines access control and multi-tenancy behavior. Four types exist:

For **GLOBAL**, **ORGANIZATION**, and **BUSINESS_UNIT** ownership configurations, field requirements, and database schema examples, see [ownership-types.md](references/ownership-types.md).

### USER
Personal/individual ownership:
```php
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'user_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id',
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
            'owner_column_name' => 'user_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id',
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
    #[ORM\JoinColumn(name: 'user_owner_id', nullable: false)]
    private $owner;
}
```

Missing organization or owner fields will cause access control failures.

## ExtendEntity Pattern

The canonical entity example above demonstrates `ExtendEntityInterface` and `ExtendEntityTrait` in combination with ownership and security configuration. The key requirements are:

1. Implement `ExtendEntityInterface`
2. Use `ExtendEntityTrait`
3. The trait provides the magic `__get`/`__set`/`__isset`/`__call` methods for runtime-added fields

## Enum Entities (Option Sets)

Enums in Oro are managed option sets (select or multiselect fields) created via `ExtendExtension` in migrations. They are not standalone entities you define in PHP — instead, you add an enum field to an existing table and Oro generates the backing entity automatically.

### Creating an Enum Field via Migration

```php
use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareTrait;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddDocumentStatusEnum implements Migration, ExtendExtensionAwareInterface
{
    use ExtendExtensionAwareTrait;

    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->extendExtension->addEnumField(
            $schema,
            'acme_demo_document',    // table name
            'status',                // field name
            'document_status',       // enum code (unique identifier)
            false,                   // multiple: false = select, true = multiselect
            false,                   // immutable
            [
                'extend' => ['owner' => ExtendScope::OWNER_CUSTOM],
            ]
        );
    }
}
```

### Loading Enum Options

Retrieve available enum choices programmatically:
```php
$this->container->get('oro_entity_extend.enum_value_provider')
    ->getEnumChoices('document_status');
```

### Constraints

- **Enum codes must be globally unique** across the entire application
- **Max 21 characters** for the enum code — Oro uses it to generate table names, and exceeding this limit causes silent failures

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
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddCustomFieldToProduct implements Migration, ExtendExtensionAwareInterface
{
    private ExtendExtensionInterface $extendExtension;

    #[\Override]
    public function setExtendExtension(ExtendExtensionInterface $extendExtension): void
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
                    'owner' => 'custom',  // Prefer ExtendScope::OWNER_CUSTOM constant
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
                    'owner' => 'custom',  // Prefer ExtendScope::OWNER_CUSTOM constant
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
3. **Misusing `#[\Override]`** — Use `#[\Override]` only on methods that genuinely implement an interface or override a parent class method (e.g., `setExtendExtension` from `ExtendExtensionAwareInterface`). Do NOT add it to custom repository methods like `findByOrganization()` — PHP 8.3+ will throw a fatal error
4. **Creating fields with nullable=true when ownership requires them** — Database constraints fail on insert
5. **Not running `oro:entity-extend:cache:clear`** — ConfigField changes won't appear in the UI

## See Also

- `references/ownership-types.md` — Complete ownership type configurations, field requirements, and database schemas
- `references/v6.1.md` — v6.1 specifics, migration checklist, and common failures
- `references/v7.0.md` — v7.0 changes (placeholder)
