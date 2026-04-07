---
name: oro-integration
description: "Use when building OroCommerce v6.1 integrations — creating import/export processors, setting up message queue consumers/producers, building integration channels and transports, configuring cron jobs, handling webhooks, or connecting Oro to external systems (ERP, PIM, payment, shipping). Relevant when the user mentions 'import', 'export', 'message queue', 'consumer', 'producer', 'integration channel', 'connector', 'cron job', 'webhook', 'async processing', or any OroCommerce integration task."
---

# OroCommerce v6.1 Integration Development

## Message Queue: Async Processing

OroCommerce uses message queues to defer heavy work outside the HTTP request cycle. Producers send messages to topics, consumers process them asynchronously.

### Processor Pattern

A processor implements `MessageProcessorInterface` and `TopicSubscriberInterface`. It must return `self::ACK` (success), `self::REJECT` (discard), or `self::REQUEUE` (retry). Never return void or null.

```php
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;

class ProcessDocumentProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    #[\Override]
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        $data = json_decode($message->getBody(), true);
        // Process data...
        return self::ACK;
    }

    #[\Override]
    public static function getSubscribedTopics(): array
    {
        return ['acme_demo.document.process'];
    }
}
```

Register with the `oro.message_queue.client.message_processor` tag:

```yaml
services:
    acme_demo.async.process_document:
        class: Acme\Bundle\DemoBundle\Async\Processor\ProcessDocumentProcessor
        arguments: ['@logger']
        tags:
            - { name: 'oro.message_queue.client.message_processor' }
```

For the full processor example, producer pattern, and message sending, see [integration-patterns.md](references/integration-patterns.md).

## Import/Export

Oro's import/export uses a pipeline: Reader -> Processor -> Writer. Each processor handles **one item at a time**.

- **Export**: Entity -> Serializer normalizes -> DataConverter -> flat array (CSV row)
- **Import**: Flat array (CSV row) -> DataConverter -> Serializer denormalizes -> Strategy -> Entity

Most custom import/export needs only a **DataConverter** and service config — no custom processor class. Use `oro_importexport.processor.export_abstract` / `oro_importexport.processor.import_abstract` as parent services.

Import processors need both `import` and `import_validation` tags. Tag attributes: `type`, `entity` (FQCN), `alias` (unique identifier).

For complete DataConverter example, service registration YAML, custom processor class, and import strategy setup, see [integration-patterns.md](references/integration-patterns.md).

## Cron Jobs

Create a command implementing `CronCommandInterface`:

```php
use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acme:cron:sync-documents')]
class SyncDocumentsCronCommand extends Command implements CronCommandInterface
{
    #[\Override]
    public function getDefaultDefinition(): string { return '0 */4 * * *'; }

    #[\Override]
    public function isActive(): bool { return true; }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Perform sync work
        return Command::SUCCESS;
    }
}
```

Schedule format is standard Linux cron: `minute hour dayOfMonth month dayOfWeek`.

## Key Pitfalls

1. **Processor return values:** Processors MUST return `self::ACK`, `self::REJECT`, or `self::REQUEUE`. Returning void or null causes messages to loop indefinitely.

2. **DBAL polling:** DBAL transport polls every second. Use AMQP (RabbitMQ) for production workloads.

3. **Message serialization:** Message bodies are JSON strings. Always `json_decode()` on receipt and `json_encode()` before sending.

For integration channels, transports, webhooks, common commands, and additional pitfalls, see [integration-patterns.md](references/integration-patterns.md).

## Version Notes

For version-specific details, see [v6.1 notes](references/v6.1.md) | [v7.0 notes](references/v7.0.md).

See also [message-queue-config.md](references/message-queue-config.md) for MQ configuration options.
