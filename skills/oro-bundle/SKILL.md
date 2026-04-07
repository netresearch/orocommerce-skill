---
name: oro-bundle
description: "Use when creating a new OroCommerce v6.1 bundle, registering bundles, setting up DependencyInjection extensions, configuring services.yml, adding translations, navigation menus, or system configuration. Also relevant for bundle-level boilerplate like compiler passes, event subscriber registration, and console commands. Also applies to 'create a bundle', 'scaffold', 'new Oro module', or general OroCommerce project structure questions."
---

# OroCommerce v6.1 Bundle Development

## Bundle Directory Structure

```
src/Acme/Bundle/DemoBundle/
├── AcmeDemoBundle.php
├── DependencyInjection/
│   └── AcmeDemoExtension.php
├── Resources/
│   ├── config/
│   │   ├── oro/
│   │   │   └── bundles.yml
│   │   ├── services.yml
│   │   ├── navigation.yml
│   │   └── system_configuration.yml
│   ├── translations/
│   │   └── messages.en.yml
│   └── views/
├── Entity/
├── EventListener/
├── Command/
└── Migrations/
    └── Data/
        └── ORM/
```

## Bundle Class

```php
<?php
namespace Acme\Bundle\DemoBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AcmeDemoBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
```

The `getPath()` method in v6.1 replaces legacy container-relative path logic, enabling the kernel to locate bundle resources automatically.

## DependencyInjection Extension

```php
<?php
namespace Acme\Bundle\DemoBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class AcmeDemoExtension extends Extension
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yml');
    }

    #[\Override]
    public function getAlias(): string
    {
        return 'acme_demo';
    }
}
```

Use `#[\Override]` attributes (standard in v6.1) — they catch method signature mismatches at compile time.

## Bundle Registration

Register in `Resources/config/oro/bundles.yml`:

```yaml
bundles:
  - { name: Acme\Bundle\DemoBundle\AcmeDemoBundle }
```

**Priority:** Lower loads first. Omit `priority` (defaults to 0) for custom bundles unless you need to override config from a specific Oro bundle.

## Service Configuration

```yaml
services:
  acme_demo.my_service:
    class: Acme\Bundle\DemoBundle\Service\MyService
    public: false

  acme_demo.event_listener.my_listener:
    class: Acme\Bundle\DemoBundle\EventListener\MyListener
    tags:
      - { name: kernel.event_listener, event: kernel.request, method: onRequest }
    arguments:
      - '@logger'
```

Tags trigger compiler passes that register listeners, commands, and API endpoints. Verify with `debug:container acme_demo`.

See [bundle-patterns.md](references/bundle-patterns.md) for service decoration, compiler passes, event listeners, navigation menus, system configuration, and translations.

## Post-Installation Commands

```bash
php bin/console cache:clear
php bin/console debug:container acme_demo  # Verify services registered
```

Cache clear is **critical** — changes to `bundles.yml`, `services.yml`, or `navigation.yml` take effect only after clearing.

## Key Pitfalls

1. **Missing cache clear:** Bundle config changes (services, navigation, system config) are invisible until `cache:clear` runs. This is the most common "it doesn't work" cause.
2. **Wrong extension alias:** The DI extension alias must match the bundle name convention (`acme_demo` for `AcmeDemoBundle`). A mismatch silently skips your `services.yml` loading.
3. **Priority ordering confusion:** In `bundles.yml`, lower priority loads first (opposite of processor priority). Most custom bundles should omit priority entirely.

## See Also

- [bundle-patterns.md](references/bundle-patterns.md) — System config, navigation, translations, compiler passes, event listeners, service decoration
- [v6.1.md](references/v6.1.md) — v6.1 specifics, migration checklist, and environment notes
- [v7.0.md](references/v7.0.md) — v7.0 changes (placeholder)
