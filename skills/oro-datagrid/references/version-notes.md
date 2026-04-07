# OroCommerce Datagrid Version Notes

## v6.1 (Current)

OroCommerce v6.1 stabilizes datagrid configuration and introduces optimizations for performance.

### Key Features
- Full YAML configuration support for all column types, filters, sorters, actions
- `onResultAfter` event for efficient relationship data attachment (replaces eager loading)
- Inline editing with full column customization
- Mass actions with individual ACL checks
- Grid extension via event listeners (preferred over core grid modification)

### YAML Structure
All datagrids follow the canonical structure:
```yaml
datagrids:
    name:
        source:
            type: orm
            query: { ... }
        columns: { ... }
        filters: { ... }
        sorters: { ... }
        actions: { ... }
        mass_actions: { ... }
        options: { ... }
```

### Configuration Files
- **Back-office API grids:** `Resources/config/oro/datagrids.yml`
- **Storefront grids:** Same file, tagged with context
- **Auto-loaded:** All bundle `datagrids.yml` files compile into DIC on kernel boot

### Performance Patterns
- Use `onResultAfter` for attaching relationship data
- Index all `data_name` columns used in filters/sorters
- Avoid unnecessary JOINs; fetch related data separately
- Cache filter choices for static enumerations

### Known Limitations
- Grid security is independent of entity-level ACL
- Inline editing requires dedicated API endpoint (PATCH)
- Filter `data_name` without sorters entry doesn't affect results
- Icon names must be valid FontAwesome class names

---

## v7.0 (Planned)

### Anticipated Changes
- **API-First Grids:** Native JSON:API datasource option (alongside ORM)
- **Typed Filter Input:** Stricter type coercion for filter values
- **Column Aliases:** Simplified column definition syntax
- **Built-in Subresources:** Native support for filtering by relationship counts
- **Performance Metrics:** Built-in query timing and slow-query detection

### Migration Path from v6.1
- Existing YAML configs will remain compatible with deprecation warnings
- Event listeners unchanged; processor interface may stabilize
- New features will be additive, no breaking changes to grid definition structure

### Upgrade Preparation
- Verify all custom processors use stable event interfaces
- Test mass action ACL definitions in your setup
- Document any custom datasource types (non-ORM)
- Audit relationship data fetching patterns

---

## Breaking Changes Between Minor Versions (v6.x)

### v6.0 → v6.1
- No breaking changes to YAML structure
- `onResultAfter` priority handling clarified (higher = runs first)
- Inline editing column options structure finalized

---

## Backward Compatibility

### Supported
- All datagrid YAML configurations from v5.4 remain valid
- Event listener interfaces stable across v6.x

### Deprecated (Still Works, Log Warning)
- Direct modification of core grid YAMLs (use event listeners instead)
- Inline ORM query SQL in datasource (use Doctrine query builder notation)

### Removed
- PHP-based grid registration (was deprecated in v5.4)

---

## Testing Checklist for v6.1 Grids

When creating or modifying grids, verify:

1. **Column Data:** All columns have `label` and `data_name` (or auto-inferred)
2. **Filter Binding:** Filters have `data_name` matching query aliases
3. **Sorters:** Sorter `data_name` paths exist in query
4. **Actions:** Row actions link to valid routes; mass actions have `acl_resource`
5. **Joins:** Necessary joins in query; bonus data via `onResultAfter`
6. **Performance:** No N+1 queries; relationship fetching batched
7. **Inline Edit:** API endpoint exists if `inline_editing.enable: true`
8. **Cache:** Run `php bin/console cache:clear` after YAML changes

---

## Useful Commands (v6.1)

```bash
# Clear datagrid cache
php bin/console cache:clear

# List registered datagrids (for debugging)
php bin/console debug:config oro_datagrid

# Test grid rendering (requires admin session)
# Navigate to the grid URL in back-office UI and inspect Network tab
```

---

## Common Issues & Solutions (v6.1)

### Grid Shows No Data
- Check `data_name` paths match query aliases
- Verify entity class path is correct
- Ensure query alias is used in columns, filters, sorters

### Sorting/Filtering Not Working
- Confirm filter/sorter has `data_name` entry
- Check `data_name` matches the query's SELECT/FROM alias
- Ensure column is `sortable: true` (if required)

### Inline Editing Fails Silently
- Verify API endpoint route exists and accepts PATCH
- Confirm entity class is correct in inline_editing config
- Check column has `save_api_accessor` with valid route

### Relationship Data Not Loading
- Use `onResultAfter` listener instead of JOIN
- Register listener with `priority: 10` to run after data load
- Batch fetch related entities in single query

### Action ACL Not Enforcing
- Verify `acl_resource` exists in `acl.yml`
- Check user has permission in security context
- Grid action ACL is independent of entity ACL
