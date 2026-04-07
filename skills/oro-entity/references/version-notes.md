# OroCommerce Entity Development — Version Notes

## v6.1 (Current)

### Key Characteristics
- **PHP Version:** 8.1+ required; 8.2+ recommended
- **Doctrine:** 2.13+ (full attribute support)
- **Database:** PostgreSQL primary; MySQL in legacy mode
- **PHP Attributes:** Mandatory; docblock annotations ignored by Doctrine

### v6.1 Entity Specifics

#### Attributes Are Mandatory (Not Optional)
```php
// v6.1 (correct)
#[ORM\Column(name: 'field_name', type: 'string')]
private $fieldName;

// v6.0 and earlier (deprecated; won't work in v6.1)
/**
 * @ORM\Column(name="field_name", type="string")
 */
private $fieldName;
```

Doctrine 2.13+ ignores docblock annotations. You **must** use PHP 8 attributes.

#### ExtendEntityInterface Pattern
Entities supporting custom fields must implement `ExtendEntityInterface`:
```php
class Document implements ExtendEntityInterface
{
    use ExtendEntityTrait;
}
```

This enables the "Entity Management" UI in System → Configuration to add custom fields at runtime.

#### Ownership Configuration is Crucial
The `#[Config]` attribute with `ownership` settings determines:
- **Access control:** Who can view/edit the entity
- **Multi-tenancy:** Organization/Business Unit/User scoping
- **Audit trails:** Which entity "owns" the record

Missing or incorrect ownership configuration causes silent access control failures.

#### Extended Field Migration Pattern
Use `ExtendExtensionInterface` to add columns to core tables:

```php
class AddCustomFieldToProduct implements Migration, ExtendExtensionAwareInterface
{
    private ExtendExtensionInterface $extendExtension;

    public function setExtendExtension(ExtendExtensionInterface $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->extendExtension->addColumn(
            $schema,
            'oro_product',  // Table name
            'custom_field',
            'string',
            ['extend' => ['is_extend' => true, 'owner' => 'custom']]
        );
    }
}
```

This method handles Oro's extended entity system automatically.

#### ConfigField Attribute for Audit/Import
```php
#[ConfigField(
    defaultValues: [
        'dataaudit' => ['auditable' => true],
        'importexport' => ['order' => 10, 'identity' => true],
    ]
)]
```

These metadata settings control:
- **dataaudit:** Whether changes are logged in the audit trail
- **importexport:** Column order and identity matching during bulk import

#### Time-Tracking Attributes
```php
#[ORM\CreatedAt]
#[ORM\Column(type: 'datetime')]
private \DateTime $createdAt;

#[ORM\UpdatedAt]
#[ORM\Column(type: 'datetime')]
private \DateTime $updatedAt;
```

These are automatically populated by Oro's lifecycle listeners.

### Important Constraints
- **Attribute parsing:** Done at runtime; no caching like old annotations
- **Database:** PostgreSQL strongly recommended; MySQL is legacy-compatibility mode
- **Ownership fields:** Must be real Doctrine columns, not derived properties
- **Repository:** Must extend `EntityRepository` or use `#[ORM\Entity(repositoryClass: ...)]`

### Common v6.1 Failures
1. **Mixing attributes and annotations** — Doctrine reads only attributes; annotations are ignored
2. **Missing organization on USER/BUSINESS_UNIT entities** — Access control silently fails
3. **Not implementing ExtendEntityInterface** — Custom fields won't appear in UI
4. **Wrong migration pattern** — Using generic `addColumn()` instead of `ExtendExtensionInterface`
5. **Forgetting `oro:entity-extend:cache:clear`** — ConfigField changes invisible until cache clears

### Migration Checklist: v6.0 → v6.1
- [ ] Replace all docblock `@ORM\` with `#[ORM\...]` attributes
- [ ] Verify all entities extend ConfigField for custom-field support
- [ ] Review ownership configuration; add organization fields if missing
- [ ] Test migrations with PostgreSQL (primary database)
- [ ] Run `oro:entity-extend:cache:clear` after entity changes
- [ ] Verify `doctrine:mapping:info` lists all entities correctly

---

## v7.0 (Future)

### Expected Changes (Provisional)

**Repository Auto-Discovery**
- Repository classes may be auto-discovered by name convention (`{Entity}Repository`)
- Manual `repositoryClass` attribute registration may be optional

**Ownership Refactoring**
- Ownership types may move from `#[Config]` metadata to dedicated configuration objects
- Possible new ownership types (e.g., `TEAM` alongside `BUSINESS_UNIT`)

**Attribute Processing**
- Lazy-loading of attribute metadata during container build (not runtime)
- Potential impact on performance and memory usage

**Extended Field Migrations**
- API for `ExtendExtensionInterface` may change syntax
- Possible deprecation of direct `addColumn()` in favor of builder pattern

**Database Support**
- MySQL likely dropped entirely
- PostgreSQL + possible SQLite support

### Likely Unchanged
- Entity attributes syntax (PHP 8 standard)
- Doctrine mapping model (ORM attributes)
- Basic migration structure

### Migration Path
- Upgrade docs will be published; test with v7.0 dev versions early
- ExtendExtensionInterface usage should stay stable; watch for deprecation notices
- Ownership configuration will have backward-compatibility layer

---

## Performance Notes (v6.1)

**Attribute Parsing Impact:**
- Attributes are parsed at runtime, not compile-time
- Large entity sets (100+ entities) may incur slight startup overhead
- Use `oro:cache` warmup in production to pre-load metadata

**Migration Best Practices:**
- Keep migration files focused (one feature per file)
- Use version subdirectories to group related schema changes
- Test migrations with `doctrine:migrations:execute --dry-run` before applying

**Extended Entities:**
- Custom fields stored in separate tables (auto-managed by Oro)
- Queries on custom fields may require table joins; use `with()` to eager-load
- Monitor query performance when extending heavily-used entities (Product, Order)

---

## Useful References
- Doctrine 2.13 Attributes: https://www.doctrine-project.org/projects/doctrine-orm/en/2.13/reference/attributes-reference.html
- Symfony 6 DQL/QueryBuilder: https://symfony.com/doc/current/doctrine/dql.html
- OroCommerce Entity Extension: See `/doc/entity` in your Oro installation
