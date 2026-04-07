---
name: oro-api
description: "Use when exposing OroCommerce v6.1 entities via REST API, configuring api.yml or api_frontend.yml, creating custom API processors, setting up filters/sorters/subresources, or working with JSON:API endpoints. Relevant when the user mentions 'API endpoint', 'expose entity via API', 'api.yml', 'REST API', 'JSON:API', 'storefront API', 'API processor', or any OroCommerce API development task."
---

# OroCommerce v6.1 REST API Configuration & Development

## Core File Locations

Two separate configs for two firewalls — never mix them:

- **Back-Office API** (`Resources/config/oro/api.yml`) — Admin users, internal integrations. Full CRUD by default.
- **Storefront API** (`Resources/config/oro/api_frontend.yml`) — Customers, PWA, mobile apps. Typically restricted to read-only with a subset of properties.

Bundle auto-discovery loads both on kernel compilation. The firewall contexts differ; storefront config in `api.yml` is silently ignored.

## Basic Entity Exposure

```yaml
# Resources/config/oro/api.yml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document: ~
```

This auto-exposes all entity properties via JSON:API endpoints (GET list/detail, POST, PATCH, DELETE).

## Field Configuration

```yaml
api:
    entities:
        Acme\Bundle\DemoBundle\Entity\Document:
            fields:
                internalId:
                    exclude: true       # Never expose
                displayName:
                    property_path: name # Rename property
                createdAt:
                    data_type: datetime
                    direction: output   # Read-only; clients can't modify
```

**Field directions:** `input` (write-only), `output` (read-only), both (default). Use `exclusion_policy: all` on sensitive entities to whitelist fields explicitly.

See [api-patterns.md](references/api-patterns.md) for exclusion policies, filter/sorter config, subresources, action disabling, and a complete entity example.

## Custom API Processors

Processors intercept requests and manipulate data:

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

Register via service tag:

```yaml
services:
    acme.api.processor.normalize_document_data:
        class: Acme\Bundle\DemoBundle\Api\Processor\NormalizeDocumentData
        tags:
            - { name: oro.api.processor, action: get, group: normalize_data, priority: 10 }
```

Processors execute in a 10-group pipeline: initialize, resource_check, normalize_input, security_check, load_data, data_security_check, transform_data, save_data, normalize_data, finalize. Most custom logic goes into `normalize_data`. Higher priority values run first.

See [processor-groups.md](references/processor-groups.md) for the full group reference with per-group context and hook guidance.

## Key Pitfalls

1. **api_frontend.yml config ignored:** Config in `api.yml` does not affect storefront. Storefront needs its own `api_frontend.yml` with separate field/action definitions.
2. **Processor priority ordering:** Higher priority = runs first. Review registration order when custom logic doesn't execute.
3. **Cache not cleared:** API caches config aggressively. Run `oro:api:cache:clear` and `cache:clear` after YAML changes. Symptoms: missing fields, broken filters, stale action states.

## See Also

- [api-patterns.md](references/api-patterns.md) — Filters, sorters, subresources, actions, exclusion policies, complete entity example
- [processor-groups.md](references/processor-groups.md) — Full processor group reference and execution order
- [v6.1.md](references/v6.1.md) — v6.1 specifics, backward compatibility, and migration notes
- [v7.0.md](references/v7.0.md) — v7.0 changes (placeholder)
