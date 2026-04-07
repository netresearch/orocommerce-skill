---
name: oro-integration
description: "Use when building OroCommerce v6.1 integrations — creating import/export processors, setting up message queue consumers/producers, building integration channels and transports, configuring cron jobs, handling webhooks, or connecting Oro to external systems (ERP, PIM, payment, shipping). Relevant when the user mentions 'import', 'export', 'message queue', 'consumer', 'producer', 'integration channel', 'connector', 'cron job', 'webhook', 'async processing', or any OroCommerce integration task."
---

# OroCommerce v6.1 Integration Development

## Message Queue: Async Processing

OroCommerce uses message queues to defer heavy work outside the HTTP request cycle. The message queue client has a producer/consumer pattern: producers send messages to topics, consumers process them asynchronously.

### Creating a Processor

A processor is a service that handles messages from a specific topic. It must implement `MessageProcessorInterface` and `TopicSubscriberInterface`:

```php
namespace Acme\Bundle\DemoBundle\Async\Processor;

use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerInterface;

class ProcessDocumentProcessor implements
    MessageProcessorInterface,
    TopicSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[\Override]
    public function process(
        MessageInterface $message,
        SessionInterface $session
    ): string
    {
        $data = json_decode($message->getBody(), true);

        try {
            $documentId = $data['document_id'] ?? null;
            if (!$documentId) {
                $this->logger->error('Missing document_id');
                return self::REJECT;
            }

            // Process the document...
            $this->logger->info("Processing document {$documentId}");

            return self::ACK;
        } catch (\Exception $e) {
            $this->logger->error("Error processing document: {$e->getMessage()}");
            return self::REQUEUE;
        }
    }

    #[\Override]
    public static function getSubscribedTopics(): array
    {
        return ['acme_demo.document.process'];
    }
}
```

**Critical:** Always return `self::ACK`, `self::REJECT`, or `self::REQUEUE`. Never return void or a string value directly.

- `ACK` — Message processed successfully; remove from queue
- `REJECT` — Message cannot be processed; discard it
- `REQUEUE` — Temporary failure; retry later

### Service Registration

Register the processor as a service with the `oro.message_queue.client.message_processor` tag:

```yaml
services:
    acme_demo.async.process_document:
        class: Acme\Bundle\DemoBundle\Async\Processor\ProcessDocumentProcessor
        arguments:
            - '@logger'
            - '@acme_demo.service.document'
        tags:
            - { name: 'oro.message_queue.client.message_processor' }
```

### Producing Messages

Inject `MessageProducerInterface` and send messages:

```php
namespace Acme\Bundle\DemoBundle\Service;

use Oro\Component\MessageQueue\Client\MessageProducerInterface;

class DocumentService
{
    public function __construct(
        private MessageProducerInterface $producer
    ) {}

    public function submitDocumentForProcessing(int $documentId): void
    {
        $this->producer->send(
            'acme_demo.document.process',
            json_encode(['document_id' => $documentId])
        );
    }
}
```

The producer accepts a topic name and a JSON string. The message is serialized and stored in the queue.

## Message Queue Configuration

OroCommerce defaults to DBAL transport (database polling), which is sufficient for development and small workloads. For production, use AMQP (RabbitMQ) for push-based, high-throughput messaging.

For transport configuration (DBAL, AMQP), consumer options, delayed messages, retry logic, and performance tuning, see [message-queue-config.md](references/message-queue-config.md).

## Consuming Messages

Start a consumer with `php bin/console oro:message-queue:consume`. It runs indefinitely, processing messages from the configured transport. Use `--time-limit=60` to exit after 60 seconds.

For consumer command options, memory limits, and restart strategies, see [message-queue-config.md](references/message-queue-config.md).

## Import/Export

Oro's import/export uses a Spring Batch-inspired pipeline: Reader → Processor → Writer. Each processor handles **one item at a time** (not batches). The framework handles iteration, batching, and persistence.

### Architecture

- **Export**: Entity → Serializer normalizes → DataConverter → flat array (CSV row)
- **Import**: Flat array (CSV row) → DataConverter → Serializer denormalizes → Strategy (find/merge/validate) → Entity

Most custom import/export needs only a **DataConverter** and service config — no custom processor class.

### Data Converters

Map between external column names and entity field names. Implement `DataConverterInterface`:

```php
namespace Acme\Bundle\DemoBundle\ImportExport\Converter;

use Oro\Bundle\ImportExportBundle\Converter\DataConverterInterface;

class DocumentDataConverter implements DataConverterInterface
{
    #[\Override]
    public function convertToExportFormat(array $exportedRecord, $skipNullValues = true): array
    {
        // Normalized entity array → flat CSV columns
        return [
            'Document Title' => $exportedRecord['title'] ?? '',
            'Created Date' => $exportedRecord['createdAt'] ?? '',
        ];
    }

    #[\Override]
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true): array
    {
        // Flat CSV columns → structured array for serializer denormalization
        return [
            'title' => $importedRecord['Document Title'] ?? '',
            'createdAt' => $importedRecord['Created Date'] ?? '',
        ];
    }
}
```

### Service Registration

Use abstract parent services — no custom processor class needed for standard cases:

```yaml
services:
    # Data Converter
    acme_demo.importexport.data_converter.document:
        class: Acme\Bundle\DemoBundle\ImportExport\Converter\DocumentDataConverter

    # Export processor (uses parent abstract + your converter)
    acme_demo.importexport.processor.export.document:
        parent: oro_importexport.processor.export_abstract
        calls:
            - [setDataConverter, ['@acme_demo.importexport.data_converter.document']]
        tags:
            - { name: oro_importexport.processor, type: export, entity: 'Acme\Bundle\DemoBundle\Entity\Document', alias: acme_document }

    # Import processor (uses parent abstract + converter + strategy)
    acme_demo.importexport.processor.import.document:
        parent: oro_importexport.processor.import_abstract
        calls:
            - [setDataConverter, ['@acme_demo.importexport.data_converter.document']]
            - [setStrategy, ['@acme_demo.importexport.strategy.document.add_or_replace']]
        tags:
            - { name: oro_importexport.processor, type: import, entity: 'Acme\Bundle\DemoBundle\Entity\Document', alias: acme_document.add_or_replace }
            - { name: oro_importexport.processor, type: import_validation, entity: 'Acme\Bundle\DemoBundle\Entity\Document', alias: acme_document.add_or_replace }

    # Import strategy (usually extends the configurable add-or-replace)
    acme_demo.importexport.strategy.document.add_or_replace:
        parent: oro_importexport.strategy.configurable_add_or_replace
```

Tag attributes: `type` (`export`, `import`, `import_validation`, `export_template`), `entity` (FQCN), `alias` (unique identifier). Import processors need both `import` and `import_validation` tags.

### Custom Processor (only when needed)

Only create a custom processor class when you need logic beyond what the DataConverter + Strategy provide. Override `process($item)` — the argument is a single entity (export) or single row array (import):

```php
namespace Acme\Bundle\DemoBundle\ImportExport\Processor;

use Oro\Bundle\ImportExportBundle\Processor\ExportProcessor;

class DocumentExportProcessor extends ExportProcessor
{
    #[\Override]
    public function process($item)
    {
        // $item is a single Document entity
        // Call parent for standard normalize + convert pipeline
        $result = parent::process($item);

        // Add custom computed fields
        $result['full_path'] = $item->getCategory()?->getPath() . '/' . $item->getTitle();

        return $result;
    }
}
```

## Integration Channels & Transports

An integration channel defines how OroCommerce connects to an external system (ERP, PIM, payment gateway, etc.). A transport implements the actual communication protocol.

### Transport Implementation

```php
namespace Acme\Bundle\DemoBundle\Transport;

use Oro\Bundle\IntegrationBundle\Transport\TransportInterface;
use Oro\Bundle\IntegrationBundle\Entity\Transport as TransportEntity;
use Oro\Bundle\IntegrationBundle\Provider\TransportSettingsInterface;

class MyApiTransport implements TransportInterface
{
    private $baseUrl;
    private $apiKey;

    #[\Override]
    public function init(TransportSettingsInterface $settings): void
    {
        $this->baseUrl = $settings->getSetting('base_url');
        $this->apiKey = $settings->getSetting('api_key');
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'My External API';
    }

    #[\Override]
    public function getSettingsFormType(): string
    {
        return MyApiTransportSettingsFormType::class;
    }

    #[\Override]
    public function getSettingsEntityFQCN(): string
    {
        return MyApiTransportSettings::class;
    }

    // Implement transport-specific methods (e.g., send, fetch)
    public function sendRequest(string $endpoint, array $payload): array
    {
        // Call external API
        // ...
        return $response;
    }
}
```

Register the transport:

```yaml
services:
    acme_demo.transport.my_api:
        class: Acme\Bundle\DemoBundle\Transport\MyApiTransport
        tags:
            - { name: 'oro_integration.transport' }
```

## Cron Jobs

Register a cron job to run a command on a schedule. Create a command and tag it with `oro_cron_command`:

```php
namespace Acme\Bundle\DemoBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'acme:cron:sync-documents', description: 'Sync documents from external source')]
class SyncDocumentsCronCommand extends Command implements CronCommandInterface
{
    #[\Override]
    public function getDefaultDefinition(): string
    {
        return '0 */4 * * *';
    }

    #[\Override]
    public function isActive(): bool
    {
        return true;
    }

    #[\Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int
    {
        // Perform sync work
        $output->writeln('Sync complete');
        return Command::SUCCESS;
    }
}
```

Register the command as a service (autoconfigure handles the tag via `CronCommandInterface`):

```yaml
services:
    acme_demo.cron.sync_documents:
        class: Acme\Bundle\DemoBundle\Command\SyncDocumentsCronCommand
        tags:
            - { name: 'console.command' }
```

The cron schedule is defined in `getDefaultDefinition()`. Standard Linux cron format: `minute hour dayOfMonth month dayOfWeek`.
- `0 */4 * * *` — Every 4 hours, at the top of the hour
- `0 9 * * 1-5` — Weekdays at 9 AM
- `0 0 1 * *` — First day of each month

## Webhooks

OroCommerce does not provide a built-in webhook framework. To implement outbound webhooks (notify external systems on entity changes), use Doctrine lifecycle events or Oro's message queue:

1. Listen to Doctrine `postPersist`/`postUpdate`/`postRemove` events
2. Send a message to the MQ with the entity change data
3. A dedicated MQ processor dispatches the HTTP webhook call asynchronously

This avoids blocking the request and handles retries via the MQ's built-in retry mechanism.

## Common Commands

```bash
# Consume messages from the queue
php bin/console oro:message-queue:consume

# Import data from a file
php bin/console oro:import-export:import /path/to/file.csv

# Export entities to a file
php bin/console oro:import-export:export Document

# List scheduled cron jobs
php bin/console oro:cron:definitions:load

# Run cron jobs manually
php bin/console oro:cron:run
```

## Key Pitfalls

1. **Processor return values:** Processors MUST return `self::ACK`, `self::REJECT`, or `self::REQUEUE`. Returning void, null, or a string won't work and may cause the message to loop indefinitely.

2. **DBAL polling:** DBAL transport polls every second by default. It's not suitable for high-volume production workloads. Use AMQP for production.

3. **Import processor entity class:** Always implement `getProcessedEntityClass()`. It tells the import framework which entity the processor handles.

4. **Integration transports:** All `TransportInterface` methods must be implemented. Missing `init()`, `getLabel()`, or settings methods will cause runtime errors.

5. **Cron timing:** Cron expressions are evaluated by the server; ensure the cron daemon is running (`php bin/console oro:cron:run`).

6. **Message serialization:** Message bodies are JSON strings. Always `json_decode()` on receipt and `json_encode()` before sending.

## Version Notes

For version-specific details, see [v6.1 notes](references/v6.1.md) | [v7.0 notes](references/v7.0.md).

See also `references/message-queue-config.md` for complete MQ configuration options.
