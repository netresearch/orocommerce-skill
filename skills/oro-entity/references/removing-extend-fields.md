# Removing Extend Fields and Attributes

Oro has **no stock hard-removal** for extend fields. Everything below is the pattern Oro core itself uses, plus the cleanup steps core omits.

## Soft Delete Is All You Get Out of the Box

- Deleting a field in the admin UI or via `oro:entity-extend:update-*` only **soft-deletes**: the field config gets `state = Deleted` / `is_deleted = true`, but the database column stays. Oro's SchemaTool runs with `saveMode = true` and never emits `DROP COLUMN`.
- A soft-deleted field **blocks clean re-creation** of a field with the same name — the stale config row is still there.

## Canonical Hard Removal

Oro's own pattern (e.g. UserBundle `v1_24` `RemoveDisableLogin`): drop the column in a migration and queue `RemoveFieldQuery` as a post query.

```php
namespace Acme\Bundle\DemoBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityConfigBundle\Migration\RemoveFieldQuery;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\ProductBundle\Entity\Product;

class RemoveWarehouseNotesField implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_product');
        if ($table->hasColumn('warehouse_notes')) {
            $table->dropColumn('warehouse_notes');
        }
        $queries->addPostQuery(new RemoveFieldQuery(Product::class, 'warehouse_notes'));
    }
}
```

`RemoveFieldQuery` hard-deletes the `oro_entity_config_field` row and scrubs `extend.schema.property`, `schema.doctrine`, and `extend.index` from the entity's config blob.

**Match the query class to the field type** — the scalar machinery half-removes enums and relations:

| Field type | Removal query |
|---|---|
| Scalar (string, text, int, ...) | `RemoveFieldQuery` |
| Enum | `RemoveEnumFieldQuery` |
| Many-to-many relation | `RemoveManyToManyRelationQuery` |

## Attributes Need Extra Cleanup

`oro_attribute_group_rel.entity_config_field_id` has **no foreign key**. Hard-deleting the field config row orphans the attribute-family assignments. Delete those rows explicitly *before* `RemoveFieldQuery`:

```php
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedSqlMigrationQuery;

$queries->addPostQuery(new ParametrizedSqlMigrationQuery(
    'DELETE FROM oro_attribute_group_rel WHERE entity_config_field_id IN (
        SELECT f.id FROM oro_entity_config_field f
        JOIN oro_entity_config e ON e.id = f.entity_id
        WHERE e.class_name = :class AND f.field_name = :field
    )',
    ['class' => Product::class, 'field' => 'warehouse_notes'],
    ['class' => Types::STRING, 'field' => Types::STRING]
));
```

The subquery form resolves the field id itself, so it must run **before** `RemoveFieldQuery` deletes the `oro_entity_config_field` row (post queries execute in the order they are added).

Oro core ships `FixBrokenDeletedFieldsQuery` to repair exactly this orphan class — if you skip the cleanup, that's the symptom you'll be repairing later.

## Removals with Unknown Timing: POST_UP Listener Migrations

When you can't know at release time whether the field will still exist (e.g. it is re-created by an import), versioned migrations are a **trap**: a skip-condition that makes `up()` a no-op still records the version as executed — it never re-runs.

Instead, add the migration from a `MigrationEvents::POST_UP` listener. Listener-added migrations are **versionless** and re-execute on **every** `oro:platform:update`, so a self-disabling precondition check works.

Note the two execution moments: the **listener** runs at migration-collection time (before any migration has executed); the **migration it adds** runs after the bundle's regular migrations. That is why the schema is guaranteed to exist when the migration executes, yet the listener itself must not assume any table exists.

- **Listener priority must be > -85** — above `UpdateExtendConfig` (-85) and `RefreshExtendCache` warmup (-255), so the extend pipeline rebuilds proxies *after* your removal.
- **Prefer POST_UP over PRE_UP**: a failing PRE_UP migration skips *all* migrations of that bundle; Oro convention keeps config mutation in post_up; and in POST_UP the schema is guaranteed to exist on fresh installs.

Inside such a migration:

1. **All writes go through queued `MigrationQuery` objects.** A `ConfigManager` flush or direct DB write in `up()` executes even under `oro:migration:load --dry-run`.
2. **Precondition guards are read-only** against live state (config rows, column existence).
3. **Guard against missing tables** (`MigrationEvent::isTableExist()` in the listener, `Schema::hasTable()` in the migration) — on a fresh install the listener fires before anything has run.

### Guard Design for Import-Managed Fields

Declare the **target** config type and remove on deviation — not "remove while `<known wrong type>`":

- An import cannot change an existing field's type, so any deviation from the target means a stale field.
- The declared target stays correct if the source system changes the type again later.
- The target is the **config type the import will create**, not the source system's type name (e.g. a PIM "Text" attribute may become a `manyToMany` `LocalizedFallbackValue` relation, **not** `text`/`string`). Verify against the field-creation code, not the source system's docs.

## Multi-Host Deployments (Oro Cloud)

`oro/multi-host` is client-side only: a console command run by hand rebuilds caches **only on its own host**. Other hosts keep extend proxies that still map the dropped column → 500s, because filesystem caches are per host. Run removals inside the deployment window (`oro:platform:update`, maintenance mode, cluster-wide cache rebuild) — another reason to use the migration-based mechanism rather than a one-off command.

## MigrationEvent API Note

`MigrationEvent` exposes only `addMigration()`, `getData()`, and `isTableExist()` — its `Connection` is protected with no accessor. A listener that needs platform-aware value conversion must inject its own `Connection`.
