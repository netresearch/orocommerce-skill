# OroCommerce API Version Notes

## v6.1 (Current)

OroCommerce v6.1 stabilizes the JSON:API implementation and introduces improvements to processor architecture.

### Key Features
- Full JSON:API compliance with filtering, sorting, pagination
- Two separate configs: `api.yml` (back-office) and `api_frontend.yml` (storefront)
- Processor-based extensibility with well-defined execution order
- Field-level exclusion and direction control
- Subresource configuration for relationship endpoints
- Flexible filter operators (eq, neq, in, nin, lt, lte, gt, gte, exists, contains, starts_with)
- Custom processors via service tags with priority-based ordering

### YAML Structure
```yaml
api:
    entities:
        EntityClass:
            exclusion_policy: all|none|custom_fields
            fields:
                fieldName:
                    exclude: true|false
                    direction: input|output
                    data_type: string|integer|datetime|...
                    property_path: mappedProperty
            filters:
                columns:
                    fieldName:
                        data_name: d.field
                        operators: [eq, neq, in]
            sorters:
                columns:
                    fieldName:
                        data_name: d.field
            actions:
                create: true|false
                update: false
                delete: false
            subresources:
                relationshipName:
                    target_class: EntityClass
                    target_association: doctrineAssociation
```

### Configuration Files
- **Back-office:** `Resources/config/oro/api.yml` (admin firewall)
- **Storefront:** `Resources/config/oro/api_frontend.yml` (frontend firewall)
- **Auto-loaded:** All bundle configs merge on kernel compile

### Processor Architecture
Processors execute in 10 sequential groups:
1. initialize
2. resource_check
3. normalize_input
4. security_check
5. load_data
6. data_security_check
7. transform_data
8. save_data
9. normalize_data (most custom logic goes here)
10. finalize

Higher priority runs first within each group.

### Cache
API configuration caches aggressively. Clear with:
```bash
php bin/console oro:api:cache:clear
php bin/console cache:clear  # Also clear general cache
```

### Known Limitations
- No built-in pagination control (uses Oro default page sizes)
- Subresource filtering requires explicit config (not inherited from entity)
- Batch operations not supported natively (use multiple requests)
- No GraphQL support (JSON:API only)

---

## v7.0 (Planned)

### Anticipated Changes
- **Typed APIs:** Stronger type validation for input/output (OpenAPI 3.0 schemas)
- **GraphQL Support:** Native GraphQL endpoint alongside JSON:API
- **Relationship Nesting:** Deeper subresource nesting (relationships of relationships)
- **Partial Updates:** PATCH with sparse fieldsets (JSON:API 1.1)
- **API Versioning:** Built-in versioning strategy for backward compatibility
- **Performance:** Query optimization, relationship eager-loading hints
- **Documentation:** Auto-generated OpenAPI documentation from config

### Migration Path from v6.1
- Existing YAML configs will work with deprecation warnings
- Processor interface unchanged; groups may be renamed
- New features additive; no breaking changes to configuration structure
- JSON:API output format remains compatible

### Upgrade Preparation
- Audit custom processors for hard-coded group names (may change)
- Test all API endpoints with newer client libraries
- Document any undocumented API contracts
- Verify filter/sorter operators work in current clients

---

## Breaking Changes Between Minor Versions (v6.x)

### v6.0 → v6.1
- No breaking changes to YAML structure
- Processor priority handling clarified
- Filter operator consistency improved
- Subresource configuration finalized

---

## Backward Compatibility

### Supported
- YAML configuration format from v5.4+
- Processor event interfaces stable across v6.x
- JSON:API output format compliant with v5.x clients
- Entity exposure via minimal config: `Entity: ~`

### Deprecated (Still Works, Log Warning)
- Inline processor registration via XML (use services.yml + tags)
- Custom action handlers (use processors instead)

### Removed
- PHP-based entity registration (was deprecated in v5.4)
- HATEOAS links (deprecated in favor of JSON:API standard)

---

## Testing Checklist for v6.1 APIs

When creating or modifying API endpoints:

1. **Entity Exposure:** Verify entity is in `api.yml` or `api_frontend.yml` (but not both unless intended)
2. **Fields:** All exposed fields have `exclude: false`; sensitive fields excluded
3. **Filters:** Filter columns have `data_name` matching entity properties
4. **Sorters:** Sorter columns have `data_name` matching entity properties
5. **Actions:** CRUD actions appropriate to audience (back-office = full; storefront = read-only)
6. **Subresources:** Subresource configs include filters/sorters if needed
7. **Processors:** Custom processors tagged correctly with action, group, priority
8. **Cache:** `php bin/console oro:api:cache:clear` run after YAML changes
9. **Endpoints:** Test via API client or curl; verify response format
10. **Security:** ACL rules restrict access; field-level security applied

---

## Useful Commands (v6.1)

```bash
# Clear API cache
php bin/console oro:api:cache:clear

# List registered API entities
php bin/console debug:config oro_api api.entities

# List registered processors
php bin/console debug:container --tag=oro.api.processor | head -20

# Test API endpoint (requires auth token)
curl -H "Authorization: Bearer <token>" \
     -H "Content-Type: application/vnd.api+json" \
     https://example.com/api/documents

# Clear all caches (safest after major changes)
php bin/console cache:clear
```

---

## Common Issues & Solutions (v6.1)

### Entity Not Exposed in API
- Check entity is in `Resources/config/oro/api.yml` or `api_frontend.yml`
- Verify bundle is registered in AppKernel
- Run `php bin/console cache:clear`
- Check logs for config parsing errors

### Filters/Sorters Not Working
- Verify filter/sorter column has `data_name` entry
- Ensure `data_name` matches Doctrine query path
- Check entity has getter for property (e.g., `getStatus()`)
- Confirm API cache cleared

### Processor Not Executing
- Check service is registered in `services.yml`
- Verify tag has correct `action` and `group` names
- Confirm `priority` is set (default may not run)
- Use `debug:container --tag=oro.api.processor` to list all

### Sensitive Fields Exposed
- Use `exclude: true` for internal fields (not accessible to clients)
- Use `direction: output` only if field is readable but not writable
- Use `exclusion_policy: all` to whitelist only public fields
- Review before API goes live

### Cache Not Clearing
- Run both `oro:api:cache:clear` AND `cache:clear`
- Delete `var/cache/prod` and `var/cache/dev` manually if needed
- Restart PHP-FPM or application server
- Check for APCu or Redis cache layer conflicts

### Storefront API Config Ignored
- Verify config is in `api_frontend.yml`, not `api.yml`
- Check firewall context is `frontend`, not `api`
- Confirm bundle extends `ApiBundle` config correctly
- Run cache clear

---

## Version Migration Guide (v6.0 → v6.1)

If upgrading from v6.0 to v6.1:

1. **YAML:** No changes needed; v6.0 configs work as-is
2. **Processors:** Verify custom processors use stable group names
3. **Tests:** Re-run API integration tests; ensure endpoints respond
4. **Filtering:** If using custom filter operators, verify they work in v6.1
5. **Documentation:** Update API docs if endpoint behavior changed

---

## API Debugging Tips

### Inspect Request/Response
```bash
# Enable API channel logging
# Edit config/packages/dev/monolog.yml to include oro_api channel
# Tail logs
tail -f var/logs/dev.log | grep "oro_api"
```

### Check Entity Config Loading
```php
// In a console command or test
$config = $this->apiConfigProvider->getConfig(Document::class);
dump($config->toArray());  // View merged config
```

### Verify Processor Order
```bash
php bin/console debug:container --tag=oro.api.processor | grep "normalize_data"
# Shows all processors in group with priority
```

### Test Endpoint Manually
```bash
# Generate API token (see Oro security docs)
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

# GET with filter
curl -H "Authorization: Bearer $TOKEN" \
     "https://app.local/api/documents?filter[status]=active&sort=-createdAt"

# POST
curl -X POST -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "documents",
         "attributes": {"subject": "New Doc", "status": "active"}
       }
     }' \
     "https://app.local/api/documents"

# PATCH
curl -X PATCH -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "documents",
         "id": "123",
         "attributes": {"status": "closed"}
       }
     }' \
     "https://app.local/api/documents/123"
```

---

## Performance Considerations (v6.1)

- **Filter operators:** `eq` is fastest; `contains` or `starts_with` slower on large datasets
- **Subresources:** Each subresource endpoint hits database; paginate large collections
- **Relationship fields:** Don't include unneeded relationship data in responses (use sparse fieldsets)
- **Processor priority:** Lower priority processors run last; useful for expensive operations
