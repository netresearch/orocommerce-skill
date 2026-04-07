# Entity Patterns Reference

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

## ConfigField Attribute (Detailed)

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
- `auditable: true` — Track changes in audit log
- `identity: true` — Use for import/export identity matching
- `order: N` — Column order in import/export

## Extending Core Entities via Migration

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

## Migration Versioning

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

## Additional Pitfalls

- **Misusing `#[\Override]`** — Use `#[\Override]` only on methods that genuinely implement an interface or override a parent class method (e.g., `setExtendExtension` from `ExtendExtensionAwareInterface`). Do NOT add it to custom repository methods like `findByOrganization()` — PHP 8.3+ will throw a fatal error
- **Creating fields with nullable=true when ownership requires them** — Database constraints fail on insert
- **Not running `oro:entity-extend:cache:clear`** — ConfigField changes won't appear in the UI
