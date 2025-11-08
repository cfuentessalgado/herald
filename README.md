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

### Event Mappings

Configure which message types should trigger which Laravel events in `config/herald.php`:

```php
'events' => [
    'user' => [
        'user.created' => App\Events\UserCreated::class,
        'user.updated' => App\Events\UserUpdated::class,
        'user.deleted' => App\Events\UserDeleted::class,
    ],

    'order' => [
        'order.created' => App\Events\OrderCreated::class,
        'order.updated' => App\Events\OrderUpdated::class,
    ],
],
```

Topics (`user`, `order`) are used to filter events when running the worker command.

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

### Creating Laravel Event Listeners

Create event classes that will be triggered when messages arrive:

```bash
php artisan make:event UserCreated
```

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}
}
```

Register listeners in `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    UserCreated::class => [
        SendWelcomeEmail::class,
        UpdateUserCache::class,
    ],
];
```

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
3. **Event Mapper** looks up the message type in your config
4. **Laravel Event** is dispatched with the payload
5. **Event Listeners** process the event (can be queued)
6. **Message Acknowledgment** marks the message as processed

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
