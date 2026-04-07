# Message Queue Configuration Reference — OroCommerce v6.1

Complete configuration guide for the Oro message queue system in v6.1. Covers transport setup, consumer options, and advanced features.

## Transport Configuration

Configure the default transport and available transports in `config/config.yml`.

### DBAL Transport (Default)

DBAL transport stores messages in a database table and polls for new messages.

```yaml
oro_message_queue:
    transport:
        default: 'dbal'
        dbal:
            connection: default              # Symfony doctrine connection name
            table: oro_message_queue         # Database table
            polling_interval: 1000           # Poll interval in milliseconds
            time_to_live: 86400              # Message TTL in seconds (0 = no limit)
```

**Parameters:**
- `connection` — Doctrine connection to use. Defaults to `default`.
- `table` — Table name for message storage. Defaults to `oro_message_queue`.
- `polling_interval` — How often (ms) the consumer checks for messages. Lower = more responsive, higher = lower CPU. Default is 1000ms.
- `time_to_live` — Seconds before a message expires. Default 0 = no expiration. Expired messages are automatically purged.

**Pros:**
- No external broker required
- Works with any Doctrine-supported database
- Simple setup

**Cons:**
- Polling-based (latency ~1 second)
- Not suitable for high-volume production (> 100 msg/sec)
- Database load increases with message volume

### AMQP Transport (Enterprise)

AMQP (RabbitMQ, etc.) uses a message broker for efficient async processing.

```yaml
oro_message_queue:
    transport:
        default: 'amqp'
        amqp:
            host: localhost
            port: 5672
            user: guest
            password: guest
            vhost: /
            lazy: false
            ssl: false
            ssl_options:
                cert_file: /path/to/cert.pem
                key_file: /path/to/key.pem
                verify_peer: true
```

**Parameters:**
- `host` — RabbitMQ server hostname
- `port` — RabbitMQ port (5672 for non-SSL, 5671 for SSL)
- `user` / `password` — RabbitMQ authentication credentials
- `vhost` — Virtual host (default `/`)
- `lazy` — Create connection on first use (true) or immediately (false)
- `ssl` — Enable SSL/TLS
- `ssl_options` — SSL certificate and verification settings

**Pros:**
- Push-based (no polling)
- Efficient for high-volume messaging
- Distributed processing

**Cons:**
- Requires external RabbitMQ broker
- More complex infrastructure

### Multiple Transports

Define multiple transports and switch between them:

```yaml
oro_message_queue:
    transport:
        default: 'dbal'
        dbal:
            connection: default
            table: oro_message_queue
        amqp:
            host: rabbitmq.example.com
            user: guest
            password: guest
```

Switch the active transport by changing the `default` key or by configuration override in environment-specific configs.

## Topic Configuration

Topics are message channels. Producers send to topics; consumers subscribe to topics.

### Topic Definition

Topics are typically defined by processors that subscribe to them:

```php
class MyProcessor implements TopicSubscriberInterface
{
    public static function getSubscribedTopics(): array
    {
        return ['acme_demo.my_topic'];
    }
}
```

OroCommerce registers processors via service tags, and topics are auto-discovered.

### Topic Naming Convention

Use lowercase, dot-separated names:
- `acme_demo.document.process`
- `oro_notification.send_notification`
- `my_integration.sync_products`

## Consumer Configuration

The message consumer consumes messages from the queue. Configure consumer behavior:

```yaml
oro_message_queue:
    consume:
        time_limit: 900                      # Max run time in seconds
        memory_limit: 1024                   # Max memory in MB
        batch_size: 10                       # Messages per batch
        log_level: info                      # Logging level
```

**Parameters:**
- `time_limit` — Consumer exits after N seconds. Default 900 (15 min). Use with cron to keep consumer fresh.
- `memory_limit` — Consumer exits if memory usage exceeds N MB. Prevents memory leaks. Default 1024 MB.
- `batch_size` — How many messages to process before committing (if broker supports). Default 10.
- `log_level` — Logging level (debug, info, warning, error). Default info.

### Consumer Command Options

Pass options to the consumer command:

```bash
# Run for 60 seconds, then exit
php bin/console oro:message-queue:consume --time-limit=60

# Run with specific memory limit
php bin/console oro:message-queue:consume --memory-limit=512

# Verbose logging
php bin/console oro:message-queue:consume -vv

# Consume from specific transport
php bin/console oro:message-queue:consume --transport=amqp
```

## Delayed Messages

Some transports support delayed message delivery. This is useful for retry logic or scheduled processing.

```php
use Oro\Component\MessageQueue\Client\Message;

$message = new Message(json_encode(['data' => 'value']));
$message->setDelay(300); // Delay 300 seconds (5 minutes)

$producer->send('acme_demo.my_topic', $message);
```

**Note:** DBAL transport doesn't natively support delayed messages. Use a custom delay mechanism or upgrade to AMQP.

## Job Processors

Some processors handle long-running jobs. Configure job processor settings:

```yaml
oro_message_queue:
    job:
        max_jobs: 50                         # Max concurrent job processors
        job_timeout: 3600                    # Job timeout in seconds (1 hour)
```

A job is a special message type that tracks status and progress. Useful for import/export and batch operations.

## Processors & Error Handling

### Processor Return Values

A processor must return one of:

```php
// Success — message removed from queue
return self::ACK;

// Failed — message discarded (not retried)
return self::REJECT;

// Transient failure — message re-queued for retry
return self::REQUEUE;
```

### Retry Logic

When a processor returns `REQUEUE`, the message goes back to the queue. The consumer will retry it.

For exponential backoff or max retries, implement in the processor:

```php
public function process(MessageInterface $message, SessionInterface $session): string
{
    $data = json_decode($message->getBody(), true);
    $retries = $data['retries'] ?? 0;

    if ($retries > 5) {
        return self::REJECT; // Give up after 5 retries
    }

    try {
        // Process...
        return self::ACK;
    } catch (\Exception $e) {
        $data['retries'] = $retries + 1;
        $this->producer->send($topic, json_encode($data));
        return self::ACK; // Acknowledge the current message, re-send with retries
    }
}
```

## Dead Letter Queue (DLQ)

Some brokers (RabbitMQ) support dead letter exchanges. When a message is rejected multiple times, it moves to a DLQ for manual inspection.

DBAL transport doesn't have a DLQ. Rejected messages are permanently deleted. Use custom logging if you need to audit rejections.

## Monitoring & Debugging

### Queue Size

Check how many messages are pending:

```bash
php bin/console oro:message-queue:status
```

### Consumer Logs

The consumer logs all activity. Monitor the log file:

```bash
tail -f var/logs/prod.log | grep message.queue
```

### Processor Discovery

List registered processors:

```bash
php bin/console debug:container --tag=oro.message_queue.client.message_processor
```

## Cron Integration

Run the consumer as a background service. One approach is a cron job that restarts the consumer periodically:

```yaml
services:
    oro_cron.message_queue.start:
        class: Oro\Bundle\CronBundle\Command\CronCommand
        tags:
            - { name: 'oro_cron_command', cron: '*/5 * * * *' }
```

This restarts the consumer every 5 minutes, clearing memory leaks and refreshing connections.

Alternatively, use systemd or supervisor to keep the consumer running continuously.

## Testing Message Queue

In tests, use a synchronous transport to process messages immediately:

```yaml
# config/config_test.yml
oro_message_queue:
    transport:
        default: 'sync'
```

The sync transport processes messages inline without queueing. Useful for unit/integration tests.

## Performance Tuning

### DBAL Tuning

For high-volume DBAL usage:
- Increase `polling_interval` to reduce database load (trade-off: higher latency)
- Add database indexes on `created_at` and `processed` columns
- Periodically purge old messages (set `time_to_live`)
- Use a separate database connection if messages are high-volume

### AMQP Tuning

For RabbitMQ:
- Configure multiple consumer instances (parallel processing)
- Use prefetch limits to balance load across consumers
- Monitor broker memory and disk usage
- Enable durable queues for persistence

## Configuration Examples

### Development Setup (DBAL)

```yaml
oro_message_queue:
    transport:
        default: 'dbal'
        dbal:
            connection: default
            table: oro_message_queue
            polling_interval: 1000
            time_to_live: 3600
    consume:
        time_limit: 60
        memory_limit: 512
```

### Production Setup (AMQP)

```yaml
oro_message_queue:
    transport:
        default: 'amqp'
        amqp:
            host: '%env(MQ_HOST)%'
            port: '%env(MQ_PORT)%'
            user: '%env(MQ_USER)%'
            password: '%env(MQ_PASSWORD)%'
            vhost: '/'
    consume:
        time_limit: 3600
        memory_limit: 2048
        batch_size: 50
```

Use environment variables for sensitive data (host, credentials).

## Troubleshooting

### Consumer Hangs

If the consumer process doesn't respond:
```bash
pkill -f 'oro:message-queue:consume'
```

Check for deadlocked processors. Review logs for errors.

### Message Not Processing

1. Verify the processor is registered: `debug:container --tag=oro.message_queue.client.message_processor`
2. Check that the topic name matches: processor's `getSubscribedTopics()` should match the producer's topic
3. Ensure the consumer is running: `ps aux | grep oro:message-queue:consume`
4. Check logs for exceptions in the processor

### Queue Backlog

If messages accumulate faster than they're processed:
- Increase `batch_size` in consumer config
- Add more consumer instances (parallel processing)
- Optimize processor logic (reduce processing time)
- Switch to AMQP for better throughput

### Memory Leaks

If consumer memory grows unbounded:
- Set `memory_limit` to force restarts
- Review processor code for resource leaks (unclosed connections, etc.)
- Use cron-based restart strategy (restart consumer every 15 min)
