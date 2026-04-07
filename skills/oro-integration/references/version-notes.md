# OroCommerce v6.1 Integration — Version Notes

This document covers v6.1-specific integration behavior and notes for migration to future versions.

## v6.1 Specifics

### Message Queue Transport

v6.1 defaults to **DBAL transport** (database polling) for out-of-the-box deployments. AMQP/RabbitMQ is available but requires separate broker infrastructure.

For development and small deployments, DBAL is sufficient. For production with high message volume (> 100 msg/sec), use AMQP.

### Import/Export Framework

The import/export system in v6.1:
- Uses processors to convert data between entity and external format
- Supports CSV, JSON, and custom formats via data converters
- Provides UI for scheduled imports and export templates
- Integrates with message queue for batch processing

### Integration Channels

Integration channels (for ERP, PIM, payment gateways, etc.) are managed via:
- `IntegrationBundle` — Channel and transport management
- Transport interface implementation
- Settings forms for user configuration

v6.1 integrations are configured via UI or YAML configuration.

### Cron Jobs

v6.1 uses `OroCronBundle` for background job scheduling. Cron jobs are:
- Registered as service tags with cron expressions
- Executed by the command `php bin/console oro:cron:run`
- Scheduled via external cron daemon (e.g., Linux cron, Windows Task Scheduler)

The cron daemon typically runs every minute and triggers due jobs.

## Known Issues & Workarounds

### DBAL Message Queue Latency

The DBAL transport polls the database for messages every ~1 second (configured via `polling_interval`). This means messages have a latency of 0-1 second before processing begins.

**Workaround:** For time-critical processing, use AMQP or increase polling frequency (trade-off: higher database load).

### Large Batch Imports Timeout

When importing large files (1000+ records), the import process may timeout if the processor is slow.

**Workaround:**
1. Implement pagination in the import processor
2. Break imports into smaller batches
3. Use message queue to defer import processing (queue job, consume asynchronously)
4. Increase PHP `max_execution_time` and `memory_limit`

### Transport Connection Pooling

DBAL transport uses a single connection. If many concurrent consumers run, connection pool exhaustion may occur.

**Workaround:**
1. Limit concurrent consumers to available connections
2. Use connection pooling (e.g., with a proxy or broker)
3. Upgrade to AMQP for better connection management

### Processor State & Idempotency

Processors may be called multiple times for the same message (due to retries). Ensure processors are idempotent: re-running them with the same input produces the same result.

**Example:**
```php
// NOT idempotent (increments count each time)
$document->setViewCount($document->getViewCount() + 1);

// Idempotent (sets to known value)
$document->setViewCount($newCount);
```

### Import/Export Memory Usage

Bulk exports of large datasets consume significant memory. The export processor loads all entities into memory before writing.

**Workaround:**
1. Use pagination/batching in the export processor
2. Increase `memory_limit` for batch processes
3. Implement streaming export (write to file incrementally)

## Migration Path: v6.1 → v7.0

OroCommerce v7.0 is in development. Expected changes:

### Likely Changes

- **Symfony 6.4 LTS** — API and configuration changes
- **RabbitMQ 4.x** — Potential API updates for AMQP transport
- **Import/Export** — May migrate to a more modern framework (e.g., Doctrine, Serializer)
- **Async Processing** — May replace custom MQ with Symfony Messenger
- **Integration Channels** — May consolidate transport APIs

### Recommended Practices for Future-Proofing

1. **Separate business logic from transport** — Don't hardcode transport details in processors
2. **Use idempotent processors** — Allows safe retries and message replay
3. **Implement proper error handling** — Return ACK/REJECT/REQUEUE appropriately
4. **Avoid custom message format** — Use JSON and keep serialization simple
5. **Test with both DBAL and AMQP** — Ensures portability
6. **Document integration workflows** — Makes migration easier

### Upgrade Path (Estimated)

When v7.0 releases, migration may involve:
- Updating processor class hierarchy (if API changes)
- Refactoring import/export processors (new base classes)
- Reconfiguring AMQP settings (if broker API changes)
- Migrating cron jobs (if cron system changes)

Oro typically provides migration guides with major releases.

## API Stability

### Stable Interfaces (v6.1)

These are unlikely to break in v7.0:
- `MessageProcessorInterface`
- `TopicSubscriberInterface`
- `MessageProducerInterface`
- `TransportInterface`
- `ImportProcessor` / `ExportProcessor`

### Semi-Stable Interfaces

These may have API changes but will likely have migration paths:
- `DataConverterInterface`
- `ContextInterface`
- Integration transport settings

### Unstable / Internal

Avoid direct use of:
- `MessageInterface` (transport-specific)
- `SessionInterface` (transport-specific)
- Internal queue implementation details

## Performance Characteristics (v6.1)

### Message Throughput

**DBAL Transport:**
- Typical throughput: 10-50 messages/second (depends on DB, processor)
- Latency: ~1 second (polling-based)
- Suitable for: Low to medium volume (< 100 msg/sec)

**AMQP Transport:**
- Typical throughput: 100+ messages/second
- Latency: ~10-100ms (push-based)
- Suitable for: High-volume production workloads

### Import/Export Performance

- Import speed: 100-1000 records/second (depends on entity complexity)
- Memory per batch: 1-10 MB (depends on entity size)
- Database writes: Can be a bottleneck for large imports

### Cron Job Performance

Cron jobs run sequentially. Heavy cron jobs can delay subsequent jobs.

**Recommendation:** Use separate cron daemons or schedule heavy jobs during off-hours.

## Monitoring & Debugging

### Queue Health

Check message queue status:
```bash
php bin/console oro:message-queue:status
```

Shows queue size and backlog.

### Message Tracing

Enable debug logging in `config_dev.yml`:
```yaml
monolog:
    handlers:
        mq_debug:
            type: stream
            path: '%kernel.logs_dir%/message_queue.log'
            level: debug
            channels: ['oro.message_queue']
```

Then tail the log:
```bash
tail -f var/logs/message_queue.log
```

### Import/Export Monitoring

The import/export UI in the back-office shows:
- Import status (pending, running, completed, failed)
- Number of processed/failed records
- Execution time

Monitor from the UI or via database queries on the job history table.

### Cron Monitoring

List scheduled cron jobs:
```bash
php bin/console oro:cron:definitions:load
```

View execution history in the database (`oro_cron_schedule` table).

## Resources

- **Official Oro Documentation:** https://doc.oroinc.com/ (update version selector to 6.1)
- **Message Queue Documentation:** https://doc.oroinc.com/backend/setup/system-requirements/message-queue/
- **Import/Export Guide:** https://doc.oroinc.com/backend/setup/system-requirements/import-export/
- **Integration Development:** https://doc.oroinc.com/backend/bundles/integration-bundle/
- **GitHub Issues:** https://github.com/oroinc/orocommerce/issues/

## Contact & Support

For issues specific to v6.1 integration development:
- Consult Oro's official documentation (version 6.1)
- File issues on GitHub (provide v6.1 version details)
- Contact Oro Enterprise support (if available)
- Community Slack/forums
