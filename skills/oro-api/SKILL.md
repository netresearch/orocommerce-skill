---
name: oro-api
description: "Use when exposing OroCommerce v6.1 entities via REST API, configuring api.yml or api_frontend.yml, creating custom API processors, setting up filters/sorters/subresources, or working with JSON:API endpoints. Relevant when the user mentions 'API endpoint', 'expose entity via API', 'api.yml', 'REST API', 'JSON:API', 'storefront API', 'API processor', or any OroCommerce API development task."
---

# OroCommerce v6.1 REST API Configuration & Development

## Core File Locations

Two separate configs for two firewalls:

### Back-Office API
```
Resources/config/oro/api.yml
```
Exposes entities behind the back-office firewall. Admin users only.

### Storefront API
```
Resources/config/oro/api_frontend.yml
```
Exposes entities behind the storefront firewall. Customers and anonymous users. **Never mix these files.**

Bundle auto-discovery loads both on kernel compilation.

## Basic Entity Exposure (v6.1 Exact)

Expose an entity with minimal config:

```yaml
# Resources/config/oro/api.yml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document: ~
```

This auto-exposes all entity properties via JSON:API endpoints:
- GET /api/documents (list)
- GET /api/documents/{id} (detail)
- POST /api/documents (create)
- PATCH /api/documents/{id} (update)
- DELETE /api/documents/{id} (delete)

## Field Configuration

Control which fields are visible, their types, and property mapping:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            fields:
                internalId:
                    exclude: true  # Never expose
                displayName:
                    property_path: name  # Rename property
                priority:
                    data_type: integer
                    direction: input  # Input only, not returned
                createdAt:
                    data_type: datetime
                    direction: output  # Output only, clients can't set
```

**Field directions:**
- `input` — Clients can POST/PATCH; not returned in GET
- `output` — Returned in GET; clients can't modify
- (both, default) — Clients can both send and receive

**Exclude policy:** `exclude: true` removes field entirely. Clients cannot request it.

## Exclusion Policies

Control defaults for all fields:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            exclusion_policy: all  # Exclude all by default; must whitelist
            fields:
                id:
                    exclude: false
                subject:
                    exclude: false
```

**Policies:**
- `none` (default) — Include all fields; exclude listed ones
- `all` — Exclude all fields; include listed ones
- `custom_fields` — Exclude dynamic custom fields; include entity properties

Use `all` for sensitive entities (users, orders) to whitelist only public fields.

## Filter Configuration

Allow filtering by specific fields:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            filters:
                columns:
                    id:
                        data_name: d.id
                    subject:
                        data_name: d.subject
                    status:
                        data_name: d.status
                        operators: [eq, neq, in, nin]
                    createdAt:
                        data_name: d.createdAt
                        operators: [eq, lt, lte, gt, gte]
```

**Common operators:** eq, neq, in, nin, lt, lte, gt, gte, exists, contains, starts_with.

Clients filter via query params: `/api/documents?filter[subject]=Hello&filter[status]=active`.

## Sorters

Allow sorting by specified fields:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            sorters:
                columns:
                    id:
                        data_name: d.id
                    subject:
                        data_name: d.subject
                    createdAt:
                        data_name: d.createdAt
```

Clients sort via query params: `/api/documents?sort=-createdAt,subject` (descending createdAt, ascending subject).

## Subresources

Expose relationships as separate endpoints:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            subresources:
                comments:
                    target_class: Acme\Bundle\DemoBundle\Entity\Comment
                    target_association: comments
```

Enables: `/api/documents/{id}/comments` (list document's comments).

Configure filters/sorters on subresources:

```yaml
subresources:
    comments:
        target_class: Acme\Bundle\DemoBundle\Entity\Comment
        target_association: comments
        filters:
            columns:
                author:
                    data_name: c.author
        sorters:
            columns:
                createdAt:
                    data_name: c.createdAt
```

## Action Configuration

Disable specific CRUD operations:

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            actions:
                delete: false  # Disallow DELETE
                delete_list: false  # Disallow batch DELETE
                create: false  # Disallow POST
                update: false  # Disallow PATCH
```

**Actions:** get, get_list, create, update, delete, delete_list, add_subresource, delete_subresource, get_subresource, get_relationship, add_relationship, delete_relationship, update_relationship.

## Custom API Processors

Processors intercept requests and manipulate data. Register in `services.yml`:

```php
namespace Acme\Bundle\DemoBundle\Api\Processor;

use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Bundle\ApiBundle\Processor\ProcessorInterface;

class NormalizeDocumentData implements ProcessorInterface
{
    #[\Override]
    public function process(ContextInterface $context): void
    {
        if ($context->hasProcessed(__METHOD__)) {
            return;
        }

        $data = $context->getResult();
        // Transform data...
        $context->setResult($data);
        $context->setProcessed(__METHOD__);
    }
}
```

Register the processor:

```yaml
services:
    acme.api.processor.normalize_document_data:
        class: Acme\Bundle\DemoBundle\Api\Processor\NormalizeDocumentData
        tags:
            - { name: oro.api.processor, action: get, group: normalize_data, priority: 10 }
```

**Tag attributes:**
- `action` — API action (get, create, update, delete, etc.)
- `group` — Processor group (see references/processor-groups.md)
- `priority` — Execution order (higher = earlier)
- `class` — Optional context class filter

See `references/processor-groups.md` for full processor group list and execution order.

## Processor Groups & Execution Order

Processors execute in a strict 10-group pipeline: initialize → resource_check → normalize_input → security_check → load_data → data_security_check → transform_data → save_data → normalize_data → finalize. Most custom logic goes into `normalize_data` (runs after all data loading).

For the full group reference with per-group context, hook guidance, and action-specific availability, see [processor-groups.md](references/processor-groups.md).

## Cache Management

API configuration is cached. After changes:

```bash
php bin/console oro:api:cache:clear
```

In production, also:

```bash
php bin/console cache:clear
```

## Complete Entity Example (v6.1)

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            exclusion_policy: all
            description: 'API endpoint for managing documents'
            fields:
                id:
                    exclude: false
                subject:
                    exclude: false
                description:
                    exclude: false
                status:
                    exclude: false
                priority:
                    exclude: false
                    data_type: integer
                internalNotes:
                    exclude: true  # Never expose
                createdAt:
                    exclude: false
                    direction: output  # Read-only
                updatedAt:
                    exclude: false
                    direction: output
            filters:
                columns:
                    subject:
                        data_name: d.subject
                    status:
                        data_name: d.status
                        operators: [eq, neq, in]
                    priority:
                        data_name: d.priority
                        operators: [eq, lt, gt, lte, gte]
            sorters:
                columns:
                    id:
                        data_name: d.id
                    subject:
                        data_name: d.subject
                    createdAt:
                        data_name: d.createdAt
            actions:
                delete_list: false  # Disallow batch delete
            subresources:
                comments:
                    target_class: Acme\Bundle\DemoBundle\Entity\Comment
                    target_association: comments
                    filters:
                        columns:
                            author:
                                data_name: c.author

api_frontend:  # Storefront API config (separate)
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            exclusion_policy: all
            fields:
                id:
                    exclude: false
                subject:
                    exclude: false
                description:
                    exclude: false
            actions:
                create: false  # Customers can't create documents
                update: false  # Customers can't edit
                delete: false  # Customers can't delete
```

## API vs api_frontend

**Back-Office API (`api.yml`)**
- Firewall: admin
- Audience: Admin users, internal integrations
- Full CRUD by default
- All entity properties available

**Storefront API (`api_frontend.yml`)**
- Firewall: frontend
- Audience: Customers, PWA, mobile apps, anonymous users
- Restricted CRUD (typically read-only)
- Subset of properties exposed

Never put storefront endpoints in `api.yml` or vice versa. The firewall context differs.

## Key Pitfalls & Solutions

### api_frontend.yml Config Ignored

Most common issue: You write config in `api.yml` expecting it to affect storefront. It won't. Storefront needs separate `api_frontend.yml`.

Check both files. They have different firewall contexts and load independently.

### Excluded Fields Are Fully Hidden

`exclude: true` removes a field completely. Clients cannot request it, even with `?include=field`. Use `direction: output` if clients need to view but not modify.

### Processor Priority Ordering

Higher priority processors run first:
```yaml
tags:
    - { name: oro.api.processor, action: get, group: normalize_data, priority: 10 }  # Runs first
    - { name: oro.api.processor, action: get, group: normalize_data, priority: 5 }   # Runs second
```

Review processor registration order when custom logic doesn't execute.

### Cache Not Cleared After Config Changes

API caches config aggressively. After YAML changes:

```bash
php bin/console oro:api:cache:clear
php bin/console cache:clear
```

Symptoms: New fields don't appear, filters don't work, actions disabled but still accessible.

## See Also

- `references/processor-groups.md` — Full processor group reference and ordering
- `references/v6.1.md` — v6.1 specifics, backward compatibility, and migration notes
- `references/v7.0.md` — v7.0 changes (placeholder)
