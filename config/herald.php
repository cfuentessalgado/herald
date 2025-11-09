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
    | Here you may configure the connection for your RabbitMQ message broker.
    | Herald uses RabbitMQ's topic exchange for efficient pattern-based routing.
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
            'exchange_type' => 'topic',
            'queue' => env('RABBITMQ_QUEUE', env('APP_NAME', 'laravel') . '-queue'),
            'queue_durable' => true,
        ],
    ],
];


