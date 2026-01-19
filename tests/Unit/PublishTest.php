<?php

use Assetplan\Herald\Connections\ConnectionInterface;
use Assetplan\Herald\HeraldManager;

it('can publish a message through the manager', function () {
    $connection = Mockery::mock(ConnectionInterface::class);

    $connection->shouldReceive('publish')
        ->once()
        ->with('user.created', ['user_id' => 123], null);

    $manager = Mockery::mock(HeraldManager::class)->makePartial();
    $manager->shouldReceive('connection')
        ->with(null)
        ->andReturn($connection);

    $manager->publish('user.created', ['user_id' => 123]);
});

it('can publish a message with custom id', function () {
    $connection = Mockery::mock(ConnectionInterface::class);

    $connection->shouldReceive('publish')
        ->once()
        ->with('order.completed', ['order_id' => 456], 'custom-id');

    $manager = Mockery::mock(HeraldManager::class)->makePartial();
    $manager->shouldReceive('connection')
        ->with(null)
        ->andReturn($connection);

    $manager->publish('order.completed', ['order_id' => 456], 'custom-id');
});

it('can publish to specific connection', function () {
    $connection = Mockery::mock(ConnectionInterface::class);

    $connection->shouldReceive('publish')
        ->once()
        ->with('payment.received', ['amount' => 99.99], null);

    $manager = Mockery::mock(HeraldManager::class)->makePartial();
    $manager->shouldReceive('connection')
        ->with('rabbitmq')
        ->andReturn($connection);

    $manager->publish('payment.received', ['amount' => 99.99], null, 'rabbitmq');
});
