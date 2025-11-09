# Herald

Announce events across your Laravel applications using message queues.

Herald enables pub-sub messaging between distributed applications. Publish events from any application (Laravel, CakePHP, legacy PHP, etc.) via RabbitMQ or Redis Streams, and consume them in Laravel where they're dispatched as native Laravel events to your Horizon queues.

## Features

- **Multiple Drivers**: RabbitMQ and Redis Streams support out of the box
- **Topic-Based Filtering**: Process only the events you care about
- **Idempotent Processing**: Automatic acknowledgment with error handling
- **Event Mapping**: Map message types to Laravel event classes
- **Signal Handling**: Graceful shutdown on SIGTERM/SIGINT
- **Queue Integration**: Events dispatched to Laravel's queue system (Horizon compatible)

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- RabbitMQ or Redis (depending on your chosen driver)

## Installation

Install via Composer:

```bash
composer require assetplan/herald
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=herald-config
```

This creates `config/herald.php` where you can configure connections and event mappings.

## Configuration

### Environment Variables

Add these to your `.env` file:

**For RabbitMQ:**
```env
HERALD_CONNECTION=rabbitmq
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=herald-events
RABBITMQ_QUEUE=my-app-queue
```

**For Redis Streams:**
```env
HERALD_CONNECTION=redis
REDIS_CONNECTION=default
REDIS_STREAM=herald-events
REDIS_CONSUMER_GROUP=my-app
REDIS_CONSUMER_NAME=worker-1
```

### Registering Handlers

Register handlers for message types in your `AppServiceProvider` or a dedicated `HeraldServiceProvider`:

```php
use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dispatch to Laravel event
        Herald::on('user.registered', \App\Events\UserRegistered::class);
        
        // Queue a job (automatically queued if it implements ShouldQueue)
        Herald::on('order.created', \App\Jobs\ProcessOrder::class);
        
        // Inline closure for quick operations (always runs synchronously)
        Herald::on('user.logout', fn (Message $msg) => Log::info("User logged out: {$msg->id}"));
        
        // Multiple handlers for the same event
        Herald::on('payment.received', \App\Jobs\SendReceipt::class);
        Herald::on('payment.received', \App\Jobs\UpdateInventory::class);
    }
}
```

**Handler Types:**

1. **Laravel Events** - Dispatched through Laravel's event system
2. **Job Classes** - Automatically queued if they implement `ShouldQueue`
3. **Service Classes** - Any class with a `handle(Message $message)` method
4. **Closures** - For quick, inline operations (always synchronous)
5. **Pre-configured Instances** - Pass configured objects directly

```php
// Pre-configured instance example
$emailSender = new \App\Services\EmailSender(
    apiKey: config('services.sendgrid.key')
);
Herald::on('email.send', $emailSender);
```

### Legacy: Config-Based Event Mappings

You can also configure event mappings in `config/herald.php` (for backward compatibility):

```php
'events' => [
    'user' => [
        'user.created' => App\Events\UserCreated::class,
        'user.updated' => App\Events\UserUpdated::class,
    ],
],
```

**Note:** `Herald::on()` registrations take priority over config-based mappings.

## Usage

### Running the Worker

Start the Herald worker to consume messages:

```bash
# Process all configured events
php artisan herald:work

# Process only 'user' topic events
php artisan herald:work user

# Process only 'order' topic events
php artisan herald:work order

# Use a specific connection
php artisan herald:work user --connection=redis
```

The worker will:
1. Connect to your message broker (RabbitMQ/Redis)
2. Consume messages matching your topic filter
3. Map message types to Laravel event classes
4. Dispatch the events (which can be queued via Horizon)
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

#### 2. Queued Handler (Heavy Operations)

For time-consuming operations, API calls, or database-intensive work:

```php
namespace App\Herald\Handlers;

use Assetplan\Herald\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessOrderPayment implements ShouldQueue
{
    use Queueable;

    public $queue = 'payments';
    public $tries = 3;
    public $backoff = [60, 120, 300];

    public function handle(Message $message): void
    {
        // Heavy operation - automatically queued
        $this->chargeCustomer($message->payload);
    }
}
```

Register it (automatically queued because of `ShouldQueue`):
```php
Herald::on('order.created', ProcessOrderPayment::class);
```

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

While Herald is primarily a consumer package, you can publish messages directly to the broker:

**RabbitMQ Example:**

```php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    config('herald.connections.rabbitmq.host'),
    config('herald.connections.rabbitmq.port'),
    config('herald.connections.rabbitmq.user'),
    config('herald.connections.rabbitmq.password'),
    config('herald.connections.rabbitmq.vhost')
);

$channel = $connection->channel();

$message = new AMQPMessage(json_encode([
    'id' => uniqid(),
    'type' => 'user.created',
    'payload' => [
        'user_id' => 123,
        'email' => 'user@example.com',
    ],
]));

$channel->basic_publish(
    $message,
    config('herald.connections.rabbitmq.exchange')
);

$channel->close();
$connection->close();
```

**Redis Streams Example:**

```php
use Illuminate\Support\Facades\Redis;

Redis::xadd(
    config('herald.connections.redis.stream'),
    '*',
    [
        'message' => json_encode([
            'id' => uniqid(),
            'type' => 'user.created',
            'payload' => [
                'user_id' => 123,
                'email' => 'user@example.com',
            ],
        ])
    ]
);
```

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

        $this->channel->basic_publish($msg, $this->exchange);
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

            $this->Flash->success('User created successfully');
            $this->redirect(array('action' => 'index'));
        }
    }
}
```

**Redis Publisher (PHP 5.6+):**

```php
<?php
// Install: composer require predis/predis:^1.1

require_once __DIR__ . '/vendor/autoload.php';

class HeraldRedisPublisher
{
    private $client;
    private $stream;

    public function __construct($config)
    {
        $this->client = new Predis\Client(array(
            'scheme' => 'tcp',
            'host'   => $config['host'],
            'port'   => $config['port'],
        ));

        $this->stream = $config['stream'];
    }

    public function publish($type, $payload)
    {
        $message = array(
            'id' => uniqid(),
            'type' => $type,
            'payload' => $payload
        );

        $this->client->xadd(
            $this->stream,
            '*',
            array('message' => json_encode($message))
        );
    }
}

// Usage
$herald = new HeraldRedisPublisher(array(
    'host' => 'localhost',
    'port' => 6379,
    'stream' => 'herald-events'
));

$herald->publish('user.created', array(
    'user_id' => 123,
    'email' => 'user@example.com'
));
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
- **type**: Event type that maps to your Laravel event class
- **payload**: Arbitrary data passed to the Laravel event constructor

## How It Works

1. **Publisher** (any app) sends a JSON message to RabbitMQ/Redis
2. **Herald Worker** consumes the message
3. **Handler Lookup** finds registered handlers for the message type
4. **Smart Execution**:
   - Closures execute immediately (sync)
   - Classes without `ShouldQueue` execute immediately (sync)
   - Classes with `ShouldQueue` are dispatched to Laravel's queue system (async)
5. **Message Acknowledgment** marks the message as processed

### Why Herald?

- **Low surface area** - One method: `Herald::on()`
- **Zero opinions** - Use events, jobs, closures, or custom classes
- **Laravel-native** - Respects `ShouldQueue`, integrates with Horizon
- **Flexible** - Handle messages however you want
- **Simple** - "We just let you know when something happens"

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
