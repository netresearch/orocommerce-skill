---
name: oro-datagrid
description: "Use when creating new OroCommerce v6.1 datagrids, adding columns to existing grids, configuring filters, sorters, actions, mass actions, inline editing, or extending core grids (like product-grid, order-grid, customer-grid). Relevant when the user mentions 'create a grid', 'add column', 'grid filter', 'datagrid', 'data table', 'list view', or grid customization in OroCommerce."
---

# OroCommerce v6.1 Datagrid Configuration & Customization

## Core File Location

Datagrids live in `Resources/config/oro/datagrids.yml` within your bundle. Bundle auto-discovery loads these on kernel compilation.

## Grid Structure (v6.1 Canonical Example)

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
                    data_name: d.subject
        sorters:
            columns:
                id:
                    data_name: d.id
            default:
                id: DESC
        actions:
            view:
                type: navigate
                label: View
                icon: eye
                link: acme_demo_document_view
                rowAction: true
```

Every grid needs: `source` (ORM query), `columns` (display), `filters` (with `data_name`), `sorters` (with `data_name`), and `actions`.

## Column Types

Most common: `string`, `integer`, `boolean`, `datetime`, `currency` (requires `currency_code` option). See `references/column-types.md` for the full list.

## Extending Existing Grids

Never modify core grid YAMLs. Use a `BuildBefore` event listener to add columns, filters, or modify config programmatically. For related entity data, use `onResultAfter` to attach data post-fetch instead of adding JOINs.

See `references/datagrid-patterns.md` for full listener examples (onBuildBefore, onResultAfter), service registration, join patterns, mass actions, and inline editing configuration.

## Key Pitfalls

1. **Column `data_name` mismatch** — If sorters/filters use `data_name: d.subject` but the query doesn't have alias `d`, sorting fails silently. Always verify alias consistency.
2. **Filter without `data_name`** — Renders in the UI but produces no WHERE clause. Users see the filter but it does nothing.
3. **ACL on grid actions is separate from entity ACL** — Both `acl_resource` on mass_actions AND entity-level ACL must pass. Define the resource in `acl.yml`.

## Cache

Datagrids are compiled into the DIC. After YAML changes: `php bin/console cache:clear`.

## See Also

- `references/column-types.md` — Full column & filter type reference
- `references/datagrid-patterns.md` — Inline editing, mass actions, listener examples, join patterns, additional pitfalls
- `references/v6.1.md` — v6.1 specifics, backward compatibility, and troubleshooting
- `references/v7.0.md` — v7.0 changes (placeholder)
