---
name: oro-bundle
description: "OroCommerce v6.1 bundle scaffolding, registration, and structure. This skill should be used when creating a new Oro bundle, registering bundles, setting up DependencyInjection extensions, configuring services.yml, adding translations, navigation menus, or system configuration. Also relevant for bundle-level boilerplate like compiler passes, event subscriber registration, and console commands. Also applies to 'create a bundle', 'scaffold', 'new Oro module', or general OroCommerce project structure questions."
---

# OroCommerce v6.1 Bundle Development

## Bundle Directory Structure

Create a standard bundle structure under `src/Vendor/Bundle/ModuleBundle/`:

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

Create the bundle entry point extending `Bundle`:

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

The `getPath()` method in v6.1 replaces the legacy container-relative path logic. It enables the kernel to locate your bundle's resources automatically.

## DependencyInjection Extension

Create `DependencyInjection/AcmeDemoExtension.php` following the v6.1 pattern with `#[\Override]` attributes:

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

Use `#[\Override]` attributes (Symfony 6.2+, standard in v6.1) — they catch method signature mismatches and improve IDE support.

## Bundle Registration

Register in `Resources/config/oro/bundles.yml`:

```yaml
bundles:
  - { name: Acme\Bundle\DemoBundle\AcmeDemoBundle, priority: 255 }
```

**Priority guidance:**
- 255: Custom bundles (loads early)
- 100: Integration bundles
- 0: Default priority

Higher priority loads dependencies first. Set 255 for new bundles unless extending another bundle.

## Service Configuration

Define services in `Resources/config/services.yml`:

```yaml
services:
  # Basic service
  acme_demo.my_service:
    class: Acme\Bundle\DemoBundle\Service\MyService
    arguments: []
    public: false

  # Tagged event listener
  acme_demo.event_listener.my_listener:
    class: Acme\Bundle\DemoBundle\EventListener\MyListener
    tags:
      - { name: kernel.event_listener, event: kernel.request, method: onRequest }
    arguments:
      - '@logger'

  # Command registration (auto-discovered in v6.1)
  Acme\Bundle\DemoBundle\Command\DemoCommand:
    tags:
      - console.command

  # Decorator pattern (common for extending Oro functionality)
  acme_demo.decorated_service:
    class: Acme\Bundle\DemoBundle\Service\DecoratedService
    decorates: original_service_name
    decoration_inner_name: original_service_name.inner
    arguments:
      - '@original_service_name.inner'
```

**Why tagging matters:** Tags trigger compiler passes that register listeners, commands, and API endpoints. Check `debug:container` to verify tags were processed.

## Navigation Menu Configuration

Create `Resources/config/navigation.yml` to add menu items:

```yaml
oro_navigation_elements:
  acme_demo_menu:
    type: group
    label: acme_demo.navigation.main_menu
    show_non_authorized: false
    children:
      acme_demo_list:
        type: item
        route: acme_demo_index
        label: acme_demo.navigation.demo_list
```

Reference the menu in your Twig template with `{{ oro_menu_render('acme_demo_menu') }}`. Translation keys are resolved during rendering.

## System Configuration

Add admin settings via `Resources/config/system_configuration.yml`:

```yaml
system_configuration:
  groups:
    acme_demo_settings:
      title: acme_demo.system_config.group_label
      icon: fa-cog
      children:
        - acme_demo_connection_settings

  fields:
    demo_api_key:
      data_type: string
      type: Oro\Bundle\ConfigBundle\Form\Type\ConfigCheckbox
      priority: 10
      ui_only: true

    demo_timeout:
      data_type: integer
      type: Symfony\Component\Form\Extension\Core\Type\IntegerType
      options:
        constraints:
          - Range:
              min: 1
              max: 60

  tree:
    system_configuration:
      platform:
        integrations:
          children:
            - acme_demo_settings
```

Settings are accessible at `/admin/config/system` and retrieved via `ConfigProvider`:

```php
$apiKey = $this->configProvider->get('demo_api_key');
```

## Translation Files

Create `Resources/translations/messages.en.yml`:

```yaml
acme_demo:
  navigation:
    main_menu: 'Demo Module'
    demo_list: 'Demo Items'

  system_config:
    group_label: 'Demo Settings'

  entity:
    class_label: 'Demo'
    class_plural_label: 'Demos'
    id.label: 'ID'
    name.label: 'Name'
```

Oro auto-discovers `.yml` translation files in `Resources/translations/`. For other languages, use `messages.fr.yml`, `messages.de.yml`, etc.

## Post-Installation Commands

After bundle changes, run:

```bash
# Clear Oro entity configuration cache (required for any service/config changes)
php bin/console cache:clear

# Verify services are registered (optional but recommended)
php bin/console debug:container acme_demo

# For database schema changes (covered in oro-entity skill)
php bin/console doctrine:migrations:migrate
```

Cache clear is **critical** — changes to bundles.yml, services.yml, or navigation.yml won't take effect until cache is cleared.

## Extending Existing Bundles

### Service Decoration

Wrap an existing service without modifying its definition:

```yaml
# In your services.yml
acme_demo.decorated_service:
  class: Acme\Bundle\DemoBundle\Service\EnhancedProductService
  decorates: oro_product.service.product_service
  decoration_inner_name: oro_product.service.product_service.inner
  arguments:
    - '@oro_product.service.product_service.inner'
```

Decoration is safer than dependency injection modification — it preserves the original service for other consumers.

### Event Listeners

Subscribe to Oro events to react to business logic:

```php
<?php
namespace Acme\Bundle\DemoBundle\EventListener;

use Oro\Bundle\EntityBundle\ORM\Event\PostFlushEntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityChangeListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PostFlushEntityEvent::NAME => 'onPostFlushEntity',
        ];
    }

    public function onPostFlushEntity(PostFlushEntityEvent $event): void
    {
        $entity = $event->getEntity();
        // Custom logic
    }
}
```

Register in services.yml with the `kernel.event_subscriber` tag (auto-subscribed).

## See Also

- `references/v6.1.md` — v6.1 specifics, migration checklist, and environment notes
- `references/v7.0.md` — v7.0 changes (placeholder)
