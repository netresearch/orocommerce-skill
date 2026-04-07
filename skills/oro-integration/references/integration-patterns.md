# Integration Patterns Reference

## Complete MQ Processor Example

A processor handles messages from a specific topic. It must implement `MessageProcessorInterface` and `TopicSubscriberInterface`:

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

## Producing Messages

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

The producer accepts a topic name and a JSON string.

## Data Converter Example

Map between external column names and entity field names. Implement `DataConverterInterface`:

```php
namespace Acme\Bundle\DemoBundle\ImportExport\Converter;

use Oro\Bundle\ImportExportBundle\Converter\DataConverterInterface;

class DocumentDataConverter implements DataConverterInterface
{
    #[\Override]
    public function convertToExportFormat(array $exportedRecord, $skipNullValues = true): array
    {
        return [
            'Document Title' => $exportedRecord['title'] ?? '',
            'Created Date' => $exportedRecord['createdAt'] ?? '',
        ];
    }

    #[\Override]
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true): array
    {
        return [
            'title' => $importedRecord['Document Title'] ?? '',
            'createdAt' => $importedRecord['Created Date'] ?? '',
        ];
    }
}
```

## Import/Export Service Registration

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

## Custom Processor (only when needed)

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

    public function sendRequest(string $endpoint, array $payload): array
    {
        // Call external API
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

## Additional Pitfalls

1. **Import processor entity class:** Always implement `getProcessedEntityClass()`. It tells the import framework which entity the processor handles.

2. **Integration transports:** All `TransportInterface` methods must be implemented. Missing `init()`, `getLabel()`, or settings methods will cause runtime errors.

3. **Cron timing:** Cron expressions are evaluated by the server; ensure the cron daemon is running (`php bin/console oro:cron:run`).
