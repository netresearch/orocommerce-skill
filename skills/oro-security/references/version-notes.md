# Security Version Notes — OroCommerce v6.1

## v6.1 Specifics

### PHP 8 Attributes for ACL

OroCommerce v6.1 fully supports PHP 8 attributes for declaring ACL requirements. This is the recommended approach over `bindings` in `acls.yml`:

```php
#[\Oro\Bundle\SecurityBundle\Attribute\Acl(
    id: 'acme_demo_document_view',
    type: 'entity',
    class: 'Acme\Bundle\DemoBundle\Entity\Document',
    permission: 'VIEW'
)]
public function viewAction(Document $document)
```

Attributes are read by `AclListener` at request time and automatically validate permissions before the controller action executes.

### Attribute Classes

Available in `Oro\Bundle\SecurityBundle\Attribute`:

- `Acl`: Full ACL declaration (entity or action type)
- `AclAncestor`: Reuse existing ACL definition (class-level only)

### AclHelper Service

The `oro_security.acl_helper` service applies ACL filtering to Doctrine QueryBuilders. This is mandatory for query filtering; ACL is NOT applied automatically.

```php
$aclHelper = $this->container->get('oro_security.acl_helper');
$qb = $repository->createQueryBuilder('d');
$aclHelper->apply($qb, 'VIEW');
$results = $qb->getQuery()->getResult();
```

Without `apply()`, all entities are returned regardless of permissions.

### Access Rules

Custom `AccessRuleInterface` implementations filter entities beyond ownership. Rules are registered via `oro_security.access_rule` tag and applied automatically.

Access rule + role-based permission must both be satisfied:

```
Role grants permission? AND Access rule allows?
├─ Yes, Yes → Grant
└─ Otherwise → Deny
```

### Ownership Field Requirements

For USER and BUSINESS_UNIT ownership:
- `owner` field is REQUIRED (mapped to user)
- `organization` field is REQUIRED (mapped to organization)

Missing either causes permission checks to fail silently. This is a frequent source of bugs when introducing ownership.

### Custom Permissions

Defined in `permissions.yml` and registered with the `security:permission:configuration:load` command. Custom permissions appear in the role matrix UI and can be checked with `isGranted('PERMISSION_NAME')`.

### Known Limitations in v6.1

1. **No attribute-level ownership**: Ownership is entity-wide, not per-attribute
2. **No dynamic field ACL**: Field permissions are static, cannot change at runtime
3. **No group-level permissions**: Permissions apply to individual users, not ad-hoc groups
4. **Query filtering is manual**: Must call `$aclHelper->apply()` explicitly

## Key Changes from v5.1

- PHP 8 attributes introduced (preferred over bindings)
- AclHelper service standardized for query filtering
- Access rules (AccessRuleInterface) expanded
- Custom permissions (permissions.yml) refined
- Ownership types stabilized (GLOBAL, ORGANIZATION, BUSINESS_UNIT, USER)

## Cache and Loading

ACL permissions are cached in the database and Symfony cache. After modifying `acls.yml` or `permissions.yml`:

```bash
bin/console cache:clear
bin/console security:permission:configuration:load
```

The `security:permission:configuration:load` command validates YAML syntax and registers permissions in the database. Run during deployment.

## Debugging

Enable SQL logging to see ACL-filtered queries:

```yaml
# config/packages/dev/doctrine.yml
doctrine:
    dbal:
        logging: true
```

View permissions in the UI:
- **System > Users > Roles**: See granted/denied permissions per role
- **System > Entities**: View entity ACL and ownership settings

Programmatically check permissions:

```php
$isGranted = $this->isGranted('VIEW', $entity);
$isGrantedField = $this->isGranted('VIEW', $entity, 'price');
```

## Upgrading to v7.0 (Placeholder)

When OroCommerce v7.0 is released, review:

- Potential removal of `bindings` in favor of attributes
- Changes in custom permission API
- Possible changes to ownership model
- Access rule interface modifications
- Field-level ACL improvements

Current v6.1 code using PHP 8 attributes will likely port smoothly to v7.0, as attributes are the forward-compatible approach.

## Migration Path: v5.1 → v6.1

If upgrading from v5.1:

1. **Update bindings to attributes**: Replace `bindings:` in `acls.yml` with `#[Acl(...)]` on controller methods. Both work in v6.1, but attributes are preferred.

2. **Verify query filtering**: Search codebase for `createQueryBuilder()` calls. Ensure each one calls `$aclHelper->apply($qb)` where ACL filtering is expected.

3. **Check ownership fields**: For USER/BUSINESS_UNIT ownership, verify all entities have `owner` and `organization` fields. Test permission resolution in the UI.

4. **Test access rules**: Any custom `AccessRuleInterface` implementations should be tested against the same rules that worked in v5.1.

5. **Run security load command**: Execute `security:permission:configuration:load` after all changes to validate and register.

## Performance Considerations

ACL checks add query complexity. For bulk operations:

- Load only IDs first, then filter by ACL
- Use `AclHelper::apply()` once per batch operation, not per entity
- Cache permission results where safe (within request scope)
- Consider queuing for large bulk exports (use operations, not direct queries)

```php
// Bad: ACL applied per entity
foreach ($entities as $entity) {
    if ($this->isGranted('VIEW', $entity)) { /* ... */ }
}

// Good: Filter at DB level
$aclHelper->apply($qb, 'VIEW');
$accessibleEntities = $qb->getQuery()->getResult();
```
