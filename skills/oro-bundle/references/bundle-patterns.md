# Bundle Configuration Patterns (v6.1)

Detailed configuration examples for OroCommerce bundle development. See the main `SKILL.md` for core concepts and quick-start patterns.

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
    demo_enabled:
      data_type: boolean
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

## Service Decoration

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

## Compiler Passes

Collect tagged services into a registry using a compiler pass:

```php
namespace Acme\Bundle\DemoBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterHandlersPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('acme_demo.handler_registry')) {
            return;
        }

        $registry = $container->getDefinition('acme_demo.handler_registry');
        foreach ($container->findTaggedServiceIds('acme_demo.handler') as $id => $tags) {
            $registry->addMethodCall('addHandler', [new Reference($id)]);
        }
    }
}
```

Register the pass in your bundle class's `build()` method:

```php
public function build(ContainerBuilder $container): void
{
    parent::build($container);
    $container->addCompilerPass(new RegisterHandlersPass());
}
```

## Event Listeners

Use standard Doctrine lifecycle events for entity change hooks:

```php
namespace Acme\Bundle\DemoBundle\EventListener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class DocumentChangeListener
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Document) {
            return;
        }
        // React to new entity creation
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Document) {
            return;
        }
        // React to entity update
    }
}
```

Register with the `doctrine.event_listener` tag:

```yaml
Acme\Bundle\DemoBundle\EventListener\DocumentChangeListener:
    tags:
        - { name: doctrine.event_listener, event: postPersist }
        - { name: doctrine.event_listener, event: postUpdate }
```
