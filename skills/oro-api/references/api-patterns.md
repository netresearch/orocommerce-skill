# API Configuration Patterns (v6.1)

Detailed configuration examples for OroCommerce REST API development. See the main `SKILL.md` for core concepts and quick-start patterns.

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

## Subresource Configuration

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

**All actions:** get, get_list, create, update, delete, delete_list, add_subresource, delete_subresource, get_subresource, get_relationship, add_relationship, delete_relationship, update_relationship.

## Complete Entity Example (v6.1)

A full example showing fields, filters, sorters, actions, subresources, and a paired storefront config:

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

## Additional Pitfalls

### Excluded Fields Are Fully Hidden

`exclude: true` removes a field completely. Clients cannot request it, even with `?include=field`. Use `direction: output` if clients need to view but not modify.

### Cache Not Cleared After Config Changes

API caches config aggressively. After YAML changes:

```bash
php bin/console oro:api:cache:clear
php bin/console cache:clear
```

Symptoms: New fields don't appear, filters don't work, actions disabled but still accessible.
