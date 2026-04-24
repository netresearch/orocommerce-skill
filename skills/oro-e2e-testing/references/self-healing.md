# Self-Healing Behat Tests

Oro's Behat framework ships a healer pipeline that intercepts step failures and attempts recovery before the scenario is marked failed. Two built-in healers exist; a tagged-service mechanism lets you add more.

## Reload Page Healer (built-in, always on)

When a step fails because an element cannot be found on the page (stale DOM, partial render, AJAX not yet settled), the Reload Page Healer refreshes the browser once and retries the step. No configuration required — it is wired up automatically and active for every e2e run.

The healer only fires on element-not-found failures. Assertion failures, step definition exceptions, and timeouts are not retried.

## OpenAI Healer (experimental, opt-in)

The OpenAI Healer sends the failing step, the page source, and surrounding context to the OpenAI API and asks for a corrected step definition. It is experimental, costs money per failure, and leaks page content to a third party — enable only for interactive test authoring, never in CI.

Enable in behat.yml:

```yaml
default: &default
  extensions: &default_extensions
    Oro\Bundle\TestFrameworkBundle\BehatOpenAIExtension\ServiceContainer\BehatOpenAIExtension:
      api_key: <OpenAI API Key>
```

The extension must be listed alongside `OroTestFrameworkExtension`. Store the key via env var substitution or a local override file — not the committed behat.yml.

## Custom Healers

Implement `Oro\Bundle\TestFrameworkBundle\Behat\Healer\HealerInterface` and register the class as a service with the `oro_test.behat.healer` tag:

```php
namespace Acme\Bundle\DemoBundle\Tests\Behat\Healer;

use Oro\Bundle\TestFrameworkBundle\Behat\Healer\HealerInterface;

class DismissOverlayHealer implements HealerInterface
{
    public function heal(/* ... framework-supplied context ... */): bool
    {
        // Attempt recovery. Return true if the step should be retried,
        // false to let the failure propagate.
    }
}
```

```yaml
services:
  acme_demo.behat.healer.dismiss_overlay:
    class: Acme\Bundle\DemoBundle\Tests\Behat\Healer\DismissOverlayHealer
    tags:
      - { name: 'oro_test.behat.healer', priority: 100 }
```

The optional `priority` attribute on the tag orders healers — higher runs first. Healers execute in order until one returns `true` (retry) or all decline (failure propagates).

## Pitfalls

1. **Assuming all failures self-heal** — only element-not-found errors reach the Reload Page Healer. Write steps that wait for the elements they need rather than relying on the healer as a general retry.
2. **OpenAI Healer in CI** — every failure triggers a paid API call and dumps page markup to OpenAI. Gate it behind a local-only behat.yml import.
3. **Missing the tag** — a healer class without the `oro_test.behat.healer` tag is never called. The container doesn't complain; the healer just doesn't run.
