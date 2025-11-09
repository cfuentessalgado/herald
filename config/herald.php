<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Herald Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default connection that will be used for
    | consuming messages. You can override this when running the worker
    | command using the --connection option.
    |
    */

    'default' => env('HERALD_CONNECTION', 'rabbitmq'),

    /*
    |--------------------------------------------------------------------------
    | Herald Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connections for your message brokers.
    | Herald supports RabbitMQ and Redis Streams out of the box.
    |
    */

    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'exchange' => env('RABBITMQ_EXCHANGE', 'herald-events'),
            'exchange_type' => 'fanout',
            'queue' => env('RABBITMQ_QUEUE', env('APP_NAME', 'laravel').'-queue'),
            'queue_durable' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CONNECTION', 'default'),
            'stream' => env('REDIS_STREAM', 'herald-events'),
            'consumer_group' => env('REDIS_CONSUMER_GROUP', env('APP_NAME', 'laravel')),
            'consumer_name' => env('REDIS_CONSUMER_NAME', gethostname()),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Mappings
    |--------------------------------------------------------------------------
    |
    | Map message types to Laravel event classes. Messages are grouped by
    | topic for easier filtering. Use the topic name when running the
    | herald:work command to process specific event types.
    |
    | Example: php artisan herald:work user
    |
    */

    'events' => [
        'user' => [
            // 'user.created' => App\Events\UserCreated::class,
            // 'user.updated' => App\Events\UserUpdated::class,
            // 'user.deleted' => App\Events\UserDeleted::class,
        ],

        'order' => [
            // 'order.created' => App\Events\OrderCreated::class,
            // 'order.updated' => App\Events\OrderUpdated::class,
        ],
    ],
];
