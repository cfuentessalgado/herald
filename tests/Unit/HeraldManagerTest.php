<?php

use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Connections\RabbitMQConnection;
use Assetplan\Herald\Connections\RedisConnection;
use Assetplan\Herald\Tests\Fixtures\UserCreatedEvent;
use Assetplan\Herald\Tests\Fixtures\OrderCreatedEvent;

beforeEach(function () {
    $this->config = [
        'default' => 'rabbitmq',
        'connections' => [
            'rabbitmq' => [
                'driver' => 'rabbitmq',
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'exchange' => 'test-exchange',
                'queue' => 'test-queue',
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'stream' => 'test-stream',
                'consumer_group' => 'test-group',
                'consumer_name' => 'test-consumer',
            ],
        ],
        'events' => [
            'user' => [
                'user.created' => UserCreatedEvent::class,
                'user.updated' => 'App\\Events\\UserUpdated',
            ],
            'order' => [
                'order.created' => OrderCreatedEvent::class,
            ],
        ],
    ];
});

it('throws exception for unconfigured connection', function () {
    $manager = new HeraldManager($this->config);
    
    expect(fn() => $manager->connection('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Connection [invalid] not configured.');
});

it('throws exception for unsupported driver', function () {
    $config = $this->config;
    $config['connections']['kafka'] = ['driver' => 'kafka'];
    
    $manager = new HeraldManager($config);
    
    expect(fn() => $manager->connection('kafka'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported driver [kafka].');
});

it('gets event class by message type', function () {
    $manager = new HeraldManager($this->config);
    
    expect($manager->getEventClass('user.created'))->toBe(UserCreatedEvent::class)
        ->and($manager->getEventClass('order.created'))->toBe(OrderCreatedEvent::class)
        ->and($manager->getEventClass('user.updated'))->toBe('App\\Events\\UserUpdated')
        ->and($manager->getEventClass('unknown.event'))->toBeNull();
});

it('gets events by topic', function () {
    $manager = new HeraldManager($this->config);
    
    $userEvents = $manager->getEventsByTopic('user');
    expect($userEvents)->toBe([
        'user.created' => UserCreatedEvent::class,
        'user.updated' => 'App\\Events\\UserUpdated',
    ]);
    
    $orderEvents = $manager->getEventsByTopic('order');
    expect($orderEvents)->toBe([
        'order.created' => OrderCreatedEvent::class,
    ]);
});

it('gets all events when topic is wildcard', function () {
    $manager = new HeraldManager($this->config);
    
    $allEvents = $manager->getEventsByTopic('*');
    expect($allEvents)->toBe([
        'user.created' => UserCreatedEvent::class,
        'user.updated' => 'App\\Events\\UserUpdated',
        'order.created' => OrderCreatedEvent::class,
    ]);
});

it('returns empty array for unknown topic', function () {
    $manager = new HeraldManager($this->config);
    
    $events = $manager->getEventsByTopic('unknown');
    expect($events)->toBe([]);
});
