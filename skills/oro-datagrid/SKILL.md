---
name: oro-datagrid
description: "OroCommerce v6.1 datagrid configuration and customization. Use this skill when creating new datagrids, adding columns to existing grids, configuring filters, sorters, actions, mass actions, inline editing, or extending core grids (like product-grid, order-grid, customer-grid). Triggers for any 'create a grid', 'add column', 'grid filter', 'datagrid', 'data table', 'list view', or grid customization in OroCommerce."
---

# OroCommerce v6.1 Datagrid Configuration & Customization

## Core File Location

Datagrids live in `Resources/config/oro/datagrids.yml` within your bundle:

```yaml
# src/Acme/Bundle/DemoBundle/Resources/config/oro/datagrids.yml
datagrids:
    document_datagrid:
        # configuration below
```

Bundle auto-discovery loads these on kernel compilation.

## Grid Structure Anatomy (v6.1 Exact)

Every datagrid follows this pattern:

```yaml
datagrids:
    my_grid:
        source:
            type: orm
            query:
                select: [d]
                from: [{ table: Acme\Bundle\DemoBundle\Entity\Document, alias: d }]
        columns:
            id:
                label: ID
            subject:
                label: Subject
        filters:
            columns:
                subject:
                    type: string
        sorters:
            columns:
                id:
                    data_name: d.id
        actions:
            view:
                type: navigate
                label: View
                icon: eye
                link: acme_demo_document_view
                rowAction: true
```

## Column Types Reference (v6.1)

Most common types (see `references/column-types.md` for the full list with examples):

- `string` — text field, renders as-is
- `integer` — numbers, right-aligned by default
- `boolean` — true/false, renders as checkmark/X
- `datetime` — full timestamp
- `currency` — currency symbol + amount (requires `currency_code` option)

## Filter Types & Configuration

Filters restrict grid data. Types: `string`, `integer`, `boolean`, `date`, `datetime`, `decimal`, `entity`, `choice`, `multiselect`.

```yaml
filters:
    columns:
        subject:
            type: string
            data_name: d.subject
        status:
            type: choice
            data_name: d.status
            options:
                field_options:
                    choices:
                        new: New
                        active: Active
                        closed: Closed
        priority:
            type: integer
        createdAt:
            type: datetime
```

**Filter clarity:** Filters generate server-side DQL WHERE clauses. The `data_name` property binds the filter to a query alias. If `data_name` is missing, the filter UI renders but produces no WHERE clause, so results are unaffected.

## Sorters: Enable Column Sorting

Sorters tie columns to sortable attributes. **Critical:** `data_name` must match your query alias.

```yaml
sorters:
    columns:
        id:
            data_name: d.id
        subject:
            data_name: d.subject
        priority:
            data_name: d.priority
    default:
        id: DESC
```

The `default` key sets initial sort order (ASC or DESC).

## Actions: Row & Bulk Operations

### Row Actions

Row actions appear as buttons per row (view, edit, delete):

```yaml
actions:
    view:
        type: navigate
        label: View
        icon: eye
        link: acme_demo_document_view
        rowAction: true
    edit:
        type: navigate
        label: Edit
        icon: pencil
        link: acme_demo_document_update
        rowAction: true
    delete:
        type: delete
        label: Delete
        icon: trash
        link: acme_demo_document_delete
        rowAction: false
```

### Mass Actions

Mass actions apply to multiple selected rows:

```yaml
mass_actions:
    delete:
        type: delete
        label: Delete Selected
        icon: trash
        acl_resource: acme_demo_document_delete
    approve:
        type: frontend
        label: Approve
        icon: check
        route: acme_demo_document_approve
        query_param_name: id
```

The `acl_resource` gates the action via ACL (separate from entity ACL — critical distinction).

## Inline Editing

Enable inline edit without navigating to a detail page:

```yaml
options:
    entity_pagination: true
    export: true
    inline_editing:
        enable: true
        entity_class: Acme\Bundle\DemoBundle\Entity\Document
        behaviour: enable_all
        column_options:
            subject:
                save_api_accessor:
                    route: acme_demo_document_patch
                    query_param: id
            status:
                data_type: string
                default_value: new
```

Inline editing requires:
1. Entity class reference
2. API endpoint that accepts PATCH
3. Column-specific configuration for form types

## Extending Existing Grids

Don't modify core grid YAMLs. Instead, register an event listener for `Oro\Bundle\DataGridBundle\Event\BuildBefore`:

```php
// src/Acme/Bundle/DemoBundle/EventListener/GridListener.php
namespace Acme\Bundle\DemoBundle\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;

class GridListener {
    public function onBuildBefore(BuildBefore $event) {
        $config = $event->getConfig();

        if ($event->getDatagrid()->getName() === 'product-grid') {
            // Add a new column
            $config->offsetSetByPath('[columns][custom_field]', [
                'label' => 'Custom Field',
                'type' => 'string',
                'data_name' => 'p.customField'
            ]);

            // Add a filter
            $config->offsetSetByPath('[filters][columns][custom_field]', [
                'type' => 'string',
                'data_name' => 'p.customField'
            ]);
        }
    }
}
```

Register the listener:

```yaml
# services.yml
services:
    acme.event_listener.grid:
        class: Acme\Bundle\DemoBundle\EventListener\GridListener
        tags:
            - { name: kernel.event_listener, event: oro_datagrid.datagrid.build.before, method: onBuildBefore }
```

## Adding Relationship Data: onResultAfter Pattern

**Performance pitfall:** Never add extra JOINs to fetch related entities in the grid query. Use `onResultAfter` to attach data post-fetch:

```php
// GridListener.php
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;

public function onResultAfter(OrmResultAfter $event) {
    if ($event->getDatagrid()->getName() !== 'document-grid') {
        return;
    }

    $records = $event->getRecords();
    $documentIds = array_map(fn($r) => $r->getId(), $records);

    // Fetch related data in a single query
    $comments = $this->doctrineRepository->findCommentsByDocumentIds($documentIds);

    // Attach to records
    foreach ($records as $record) {
        $data = $record->getData();
        $data['comment_count'] = count($comments[$record->getId()] ?? []);
        $record->setData($data);
    }
}
```

Register with higher priority than data loading:

```yaml
tags:
    - { name: kernel.event_listener, event: oro_datagrid.orm_datasource.result.after, method: onResultAfter, priority: 10 }
```

## Grid with Joined Entities

When you need a join for filtering/sorting (unavoidable), add it directly to the query:

```yaml
datagrids:
    document_grid:
        source:
            type: orm
            query:
                select: [d, IDENTITY(d.author) as author_id]
                from:
                    - { table: Acme\Bundle\DemoBundle\Entity\Document, alias: d }
                join:
                    left:
                        - { join: d.author, alias: a }
                where:
                    and:
                        - a.active = true
        columns:
            author_name:
                label: Author
                data_name: a.name
        filters:
            columns:
                author_name:
                    type: string
                    data_name: a.name
        sorters:
            columns:
                author_name:
                    data_name: a.name
```

**Why:** If the join is essential for the grid's function (not bonus data), keep it in the query. But ask yourself first: can I fetch this in `onResultAfter`?

## Key Pitfalls & Solutions

### ACL on Grid Actions ≠ Entity ACL

Grid action ACL (`acl_resource` in mass_actions) is **separate** from entity ACL. Both must pass:

```yaml
mass_actions:
    delete:
        acl_resource: acme_demo_document_delete  # Checks user permission to DELETE this resource
```

Your ACL config must define this resource in `acl.yml`.

### Column `data_name` Mismatch

If sorters/filters use `data_name: d.subject` but the query doesn't have alias `d`, sorting fails silently. **Always verify alias consistency.**

### Inline Editing Without API Endpoint

Inline editing requires a PATCH API endpoint. If missing, edits silently fail. Provide the full route:

```yaml
inline_editing:
    column_options:
        status:
            save_api_accessor:
                route: acme_demo_document_patch  # Must exist and accept PATCH
                query_param: id
```

### Filter Display Without Effect

A filter without `data_name` renders in the UI but doesn't filter. Users get confused. Always bind filters to sortable columns or explicitly set `data_name`.

## Cache & Compilation

Datagrids are compiled into the DIC. After YAML changes:

```bash
php bin/console cache:clear
```

In production, rebuild the container cache.

## See Also

- `references/column-types.md` — Full column & filter type reference
- `references/v6.1.md` — v6.1 specifics, backward compatibility, and troubleshooting
- `references/v7.0.md` — v7.0 changes (placeholder)
