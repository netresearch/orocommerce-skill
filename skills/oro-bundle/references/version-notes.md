# OroCommerce Bundle Development — Version Notes

## v6.1 (Current)

### Key Characteristics
- **PHP Version:** 8.1+ required; 8.2+ recommended
- **Symfony:** 6.x base (Symfony 6.2+ for full attribute support)
- **Database:** PostgreSQL as primary; MySQL support reduced to legacy compatibility mode
- **PHP Attributes:** Full adoption; docblock annotations deprecated

### v6.1 Specifics

#### Attributes Replace Annotations
All Symfony/Doctrine attributes now use PHP 8 native syntax:
```php
// v6.1 (correct)
#[\Override]
public function load(array $configs, ContainerBuilder $container): void { }

// v6.0 and earlier (deprecated)
@Override
```

#### Bundle::getPath() Required
```php
// v6.1
public function getPath(): string
{
    return __DIR__;
}

// v6.0 (legacy, still works but discouraged)
public function getNamespace()
{
    return __NAMESPACE__;
}
```

#### Service Registration
- **Auto-discovery** of console commands and subscribers in v6.1
- PSR-4 auto-wiring enabled by default in `config/services.yaml`
- Service tags must be explicitly defined in bundle's `services.yml`

#### DependencyInjection Extension
Extension class naming: `{BundleName}Extension` in `DependencyInjection/` directory.

### Important Constraints
- **PostgreSQL**: Primary database; test with PostgreSQL before production
- **Entity metadata**: Attributes read at runtime; no caching of attribute metadata (unlike annotations)
- **Memory usage**: Attribute parsing is slightly heavier than cached annotations — monitor in large projects

### Common Pitfalls in v6.1
1. **Forgetting to use PHP 8 attributes** — code expecting old `@` annotations will fail silently
2. **Not clearing cache after bundle changes** — bundles.yml changes require `cache:clear`
3. **Incorrect service priorities** — higher values load first; use 255 for custom bundles
4. **Missing getPath()** — Bundle class won't be discovered if this method is absent

---

## v7.0 (Future)

### Expected Changes (Provisional)

**Event System Modernization**
- Possible move to attribute-based event dispatch (e.g., `#[EventListener]` on methods)
- Listener auto-registration may change from service tags to attribute scanning

**Lazy Service Loading**
- Containers may default to lazy-loading all services to reduce startup time
- Decoration pattern may change syntax

**Database Support**
- MySQL likely dropped entirely; PostgreSQL + SQLite possible

**Bundle Auto-Discovery**
- Manual registration in `bundles.yml` may be replaced by auto-discovery from `composer.json`

### Migration Path
- Upgrade guides will be published; no breaking changes expected in minor updates
- Start testing with v7.0 dev/beta versions in your CI/CD pipeline
- Services and listeners using attributes will upgrade smoothly; service decoration may need review

---

## Migration Checklist: v6.0 → v6.1

- [ ] Replace all `@` annotations with `#[...]` attributes
- [ ] Add `getPath()` method to Bundle classes
- [ ] Update `services.yml` to use explicit tag definitions (no auto-discovery from class names)
- [ ] Test with PostgreSQL (if not already done)
- [ ] Run `cache:clear` to rebuild service container
- [ ] Verify `php bin/console debug:container` shows all expected services

---

## Useful References
- Symfony 6 Attributes: https://symfony.com/doc/current/service_container/attributes.html
- OroCommerce v6.1 Upgrade: See official upgrade docs in `/doc` directory
