# Herald

Announce events across your Laravel applications using RabbitMQ.

Herald enables pub-sub messaging between distributed applications. Publish events from any application (Laravel, CakePHP, legacy PHP, etc.) via RabbitMQ, and consume them in Laravel where they're dispatched as native Laravel events to your Horizon queues.

## Features

- **Pattern-Based Routing**: Subscribe to specific event patterns using RabbitMQ's topic exchange (`user.*`, `order.#`, etc.)
- **Efficient Message Filtering**: Broker-level routing ensures consumers only receive relevant events
- **Handler Registration**: Simple `Herald::on()` API for mapping message types to handlers
- **Flexible Handler Types**: Queued jobs, sync handlers, closures, or object instances
- **Idempotent Processing**: Automatic acknowledgment with error handling
- **Signal Handling**: Graceful shutdown on SIGTERM/SIGINT
- **Queue Integration**: Handlers dispatched to Laravel's queue system (Horizon compatible)

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- RabbitMQ

## Installation

Install via Composer:

```bash
composer require assetplan/herald
```

**That's it!** Herald automatically registers via Laravel package discovery.

### Setting Up Handler Registration

Run the install command to publish a dedicated service provider for registering your message handlers:

```bash
php artisan herald:install
```

This creates `app/Providers/HeraldServiceProvider.php` where you can register your handlers. Add it to your `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\HeraldServiceProvider::class,
],
```

**Note:** If you're using Laravel 11+ with automatic provider discovery, the provider will be automatically registered.

### Optional: Customize Connection Settings

If you need to customize connection settings beyond environment variables, you can optionally publish the configuration file:

```bash
php artisan vendor:publish --tag=herald-config
```

**Most users won't need to publish the config.** Just set your environment variables and you're good to go.

## Configuration

Herald uses environment variables for configuration. Add these to your `.env` file:

```env
HERALD_CONNECTION=rabbitmq
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=herald-events
RABBITMQ_QUEUE=my-app-queue  # Each application should have its own queue name
```

**Note:** Herald uses a **topic exchange**, which enables efficient pattern-based routing. Each application has its own queue bound to the exchange, and workers subscribe to specific event patterns (e.g., `user.*`, `order.#`).

### Registering Handlers

After running `php artisan herald:install`, register handlers in `app/Providers/HeraldServiceProvider.php`:

```php
use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;

class HeraldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Queued job (automatically queued if it implements ShouldQueue)
        Herald::on('order.created', \App\Jobs\ProcessOrder::class);
        
        // Sync handler (executes immediately)
        Herald::on('cache.invalidate', \App\Handlers\CacheInvalidator::class);
        
        // Closure for quick operations (always runs synchronously)
        Herald::on('user.logout', fn (Message $msg) => Log::info("User logged out: {$msg->id}"));
        
        // Multiple handlers for the same event
        Herald::on('payment.received', \App\Jobs\SendReceipt::class);
        Herald::on('payment.received', \App\Jobs\UpdateInventory::class);

        // Same handler for multiple events
        Herald::onAny([
            'property.pricing.updated',
            'property.reservation.fallen',
            'property.photo.updated',
        ], \App\Jobs\UpdateUnitIndex::class);
        
        // Legacy job adapter - bridge to existing jobs
        Herald::on('user.registered', function (Message $msg) {
            \App\Jobs\SendWelcomeEmail::dispatch(
                userId: $msg->payload['user_id'],
                email: $msg->payload['email']
            );
        });
    }
}
```

**Tip:** The published service provider includes detailed examples and documentation for all handler types.

**Handler Types:**

1. **Queued Jobs** - Implement `ShouldQueue`, dispatched with YOUR queue settings
2. **Sync Handlers** - Classes with `handle(Message $message)` method
3. **Closures** - For quick operations or adapting legacy jobs
4. **Pre-configured Instances** - Pass configured objects directly

```php
// Pre-configured instance example
$emailSender = new \App\Services\EmailSender(
    apiKey: config('services.sendgrid.key')
);
Herald::on('email.send', $emailSender);
```

## Usage

### Running the Worker

Start the Herald worker to consume messages:

```bash
# Process all events (subscribes to all routing keys)
php artisan herald:work '*'

# Process only 'user.*' events (user.created, user.updated, etc.)
php artisan herald:work 'user.*'

# Process specific event
php artisan herald:work 'order.shipped'

# Process multiple patterns using wildcards
# * matches exactly one word
# # matches zero or more words
php artisan herald:work 'user.*.verified'  # Matches: user.email.verified
php artisan herald:work 'order.#'          # Matches: order.created, order.payment.completed

# Use a specific connection (if you have multiple RabbitMQ connections configured)
php artisan herald:work 'user.*' --connection=rabbitmq
```

### Listing Registered Handlers

See which events are registered and how they will run:

```bash
php artisan herald:list
```

#### Topic Pattern Matching

Herald uses RabbitMQ's topic exchange for efficient message routing:

- **`*` (asterisk)** - matches exactly one word (e.g., `user.*` matches `user.created`, `user.deleted`)
- **`#` (hash)** - matches zero or more words (e.g., `order.#` matches `order.created`, `order.payment.completed`)
- **Exact match** - subscribe to a specific event (e.g., `user.created`)

**Examples:**
- `user.*` - All user events (`user.created`, `user.updated`, `user.deleted`)
- `*.created` - All creation events (`user.created`, `order.created`, `product.created`)
- `user.*.verified` - Events like `user.email.verified`, `user.phone.verified`
- `order.#` - All order-related events, including nested ones
- `#` - All events

The worker will:
1. Connect to RabbitMQ
2. Subscribe only to messages matching your topic pattern
3. Execute registered handlers for each message type
4. Dispatch queued handlers to Laravel's queue system (Horizon compatible)
5. Acknowledge successful processing

### Creating Handlers

Herald gives you full flexibility in how you handle messages. Here are the different approaches:

#### 1. Synchronous Handler (Fast Operations)

For quick operations that complete in milliseconds:

```php
namespace App\Herald\Handlers;

use Assetplan\Herald\Message;
use Illuminate\Support\Facades\Log;

class LogUserActivity
{
    public function handle(Message $message): void
    {
        Log::info('User activity', [
            'event_id' => $message->id,
            'event_type' => $message->type,
            'data' => $message->payload,
        ]);
    }
}
```

Register it:
```php
Herald::on('user.activity', LogUserActivity::class);
```

#### 2. Laravel Job (Heavy Operations)

For time-consuming operations, API calls, or database-intensive work, use a standard Laravel Job:

```php
namespace App\Jobs;

use Assetplan\Herald\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'payments';
    public $tries = 3;
    public $backoff = [60, 120, 300];

    public function __construct(public Message $message)
    {
    }

    public function handle(): void
    {
        // Heavy operation - automatically queued
        $this->chargeCustomer($this->message->payload);
    }
}
```

Register it (automatically queued because it implements `ShouldQueue`):
```php
Herald::on('order.created', ProcessOrderPayment::class);
```

**Note:** When using a Laravel Job as a Herald handler, your `__construct()` method should receive the `public Message $message` parameter. Herald will instantiate the job with the message and dispatch it to your queue.

#### 3. Laravel Event Handler

Dispatch to Laravel's event system for complex workflows:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class UserRegistered
{
    use Dispatchable;

    public function __construct(public array $data) {}
}
```

Register listeners in `EventServiceProvider`:
```php
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        CreateUserProfile::class,
        NotifyAdmins::class,
    ],
];
```

Register with Herald:
```php
Herald::on('user.registered', UserRegistered::class);
```

#### 4. Closure Handler (Prototyping/Simple Logic)

For quick operations or prototyping (always runs synchronously):

```php
Herald::on('cache.clear', fn (Message $msg) => Cache::forget($msg->payload['key']));
Herald::on('user.logout', fn (Message $msg) => Log::info("User {$msg->payload['user_id']} logged out"));
```

**Pro tip:** Closures are great for development, but use proper classes in production for better testability and maintainability.

### Publishing Messages from Laravel

Herald provides a simple `publish()` method for sending messages:

```php
use Assetplan\Herald\Facades\Herald;

// Simple publish
Herald::publish('user.created', [
    'user_id' => 123,
    'email' => 'user@example.com',
]);

// Publish with custom message ID
Herald::publish('order.completed', [
    'order_id' => 456,
    'total' => 99.99,
], id: 'custom-id-123');

// Publish to specific connection
Herald::publish('index.rebuild', [
    'entity_id' => 789,
], connection: 'rabbitmq');
```

The routing key (first parameter) is used for topic-based routing, allowing consumers to subscribe to specific event patterns.

### Publishing from Legacy PHP Applications

Herald works with any publisher that can send JSON messages. Here's a PHP 5.6+ example for CakePHP or other legacy applications:

**RabbitMQ Publisher (PHP 5.6+):**

```php
<?php
// Install: composer require php-amqplib/php-amqplib:^2.12

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class HeraldPublisher
{
    private $connection;
    private $channel;
    private $exchange;

    public function __construct($config)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost']
        );

        $this->channel = $this->connection->channel();
        $this->exchange = $config['exchange'];
    }

    public function publish($type, $payload)
    {
        $message = array(
            'id' => uniqid(),
            'type' => $type,
            'payload' => $payload
        );

        $msg = new AMQPMessage(
            json_encode($message),
            array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
        );

        $this->channel->basic_publish($msg, $this->exchange, $type);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

// Usage in CakePHP Controller
class UsersController extends AppController
{
    public function add()
    {
        $user = $this->User->save($this->request->data);

        if ($user) {
            $herald = new HeraldPublisher(array(
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'exchange' => 'herald-events'
            ));

            $herald->publish('user.created', array(
                'user_id' => $user['User']['id'],
                'email' => $user['User']['email'],
                'created_at' => $user['User']['created']
            ));

            $herald->close();
        }
    }
}
```

## Message Format

Herald expects messages in this JSON format:

```json
{
    "id": "unique-message-id",
    "type": "user.created",
    "payload": {
        "user_id": 123,
        "email": "user@example.com"
    }
}
```

- **id**: Unique identifier for the message (for deduplication/logging)
- **type**: Message type that maps to your registered handlers (e.g., 'user.created', 'order.shipped')
- **payload**: Arbitrary data passed to your handlers (accessible via `$message->payload`)

## How It Works

1. **Publisher** (any app) sends a JSON message to RabbitMQ
2. **Herald Worker** (`herald:work`) consumes the message
3. **Handler Lookup** finds registered handlers via `Herald::on()` for the message type
4. **Smart Dispatch**:
   - **Closures**: Execute immediately (sync) - perfect for quick operations
   - **Sync Handlers**: Classes without `ShouldQueue` execute immediately (sync)
   - **Queued Handlers**: Classes with `ShouldQueue` are dispatched directly as `YourJob::dispatch($message)` (async)
5. **Message Acknowledgment** marks the message as processed in the broker

### Why Herald?

- **Low surface area** - One registration method: `Herald::on()`
- **Zero opinions** - Use jobs, closures, events, or any handler pattern you prefer
- **Laravel-native** - Your queued jobs dispatch with YOUR settings (queue name, retries, backoff, etc.)
- **No magic** - Queued handlers dispatch as `YourJob::dispatch($message)` - that's it
- **No wrapper jobs** - Your job appears in Horizon logs as itself, not wrapped
- **Works out of the box** - Config publishing is completely optional
- **Flexible** - Handle messages however you want: sync, async, or mixed
- **Simple contract** - Herald delivers `Message`, you decide what to do with it

## Deployment

### Supervisor Configuration

Run Herald workers with Supervisor for production:

```ini
[program:herald-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan herald:work user
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/herald.log
stopwaitsecs=3600
```

Reload Supervisor after creating the config:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start herald-worker:*
```

### Docker

Example Dockerfile for Herald workers:

```dockerfile
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader

CMD ["php", "artisan", "herald:work"]
```

## Quick Reference

### Handler Registration

```php
// Class string (resolved from container)
Herald::on('event.type', HandlerClass::class);

// Object instance (pre-configured)
Herald::on('event.type', new Handler($config));

// Closure (always sync)
Herald::on('event.type', fn (Message $msg) => /* ... */);

// Multiple handlers
Herald::on('event.type', FirstHandler::class);
Herald::on('event.type', SecondHandler::class);
```

### Handler Execution Rules

| Handler Type | Implements `ShouldQueue` | Execution |
|-------------|-------------------------|-----------|
| Closure | N/A | Always synchronous |
| Class | ✅ Yes | Queued (async) |
| Class | ❌ No | Synchronous |
| Object instance | ✅ Yes | Queued (async) |
| Object instance | ❌ No | Synchronous |

### Message Object

All handlers receive a `Message` object:

```php
$message->id;       // Unique message ID
$message->type;     // Event type (e.g., 'user.created')
$message->payload;  // Array of data
```

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Cristian Fuentes Salgado](https://github.com/cfuentes)

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/assetplan/herald/issues).
