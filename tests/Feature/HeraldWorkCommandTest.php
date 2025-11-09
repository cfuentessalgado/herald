<?php

use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Tests\Fixtures\UserCreatedEvent;
use Assetplan\Herald\Tests\Fixtures\UserUpdatedEvent;

// These are simplified feature tests that test the logic without needing real connections
// Full integration tests would require actual RabbitMQ/Redis instances

it('command exists and is registered', function () {
    expect(
        $this->app->make('Illuminate\Contracts\Console\Kernel')
            ->all()
    )->toHaveKey('herald:work');
});

it('displays error when topic has no event mappings', function () {
    config(['herald.events' => []]);
    \Assetplan\Herald\Facades\Herald::clearHandlers();

    $this->artisan('herald:work', ['topic' => 'nonexistent'])
        ->expectsOutput('No handlers registered for topic: nonexistent')
        ->assertExitCode(1);
});

it('herald manager gets event class by type', function () {
    $manager = app(HeraldManager::class);

    expect($manager->getEventClass('user.created'))
        ->toBe(UserCreatedEvent::class);
});

it('herald manager gets events by topic', function () {
    $manager = app(HeraldManager::class);

    $userEvents = $manager->getEventsByTopic('user');

    expect($userEvents)->toHaveKey('user.created')
        ->and($userEvents)->toHaveKey('user.updated');
});

it('herald manager gets all events with wildcard', function () {
    $manager = app(HeraldManager::class);

    $allEvents = $manager->getEventsByTopic('*');

    expect($allEvents)->toHaveCount(3)
        ->and($allEvents)->toHaveKey('user.created')
        ->and($allEvents)->toHaveKey('order.created');
});

it('event classes exist and are instantiable', function () {
    $userCreatedEvent = new UserCreatedEvent(['user_id' => 1]);
    $userUpdatedEvent = new UserUpdatedEvent(['user_id' => 1]);

    expect($userCreatedEvent)->toBeInstanceOf(UserCreatedEvent::class)
        ->and($userCreatedEvent->data)->toBe(['user_id' => 1])
        ->and($userUpdatedEvent)->toBeInstanceOf(UserUpdatedEvent::class);
});

it('config has correct structure', function () {
    $config = config('herald');

    expect($config)->toHaveKey('default')
        ->and($config)->toHaveKey('connections')
        ->and($config)->toHaveKey('events')
        ->and($config['connections'])->toHaveKey('rabbitmq')
        ->and($config['connections'])->toHaveKey('redis')
        ->and($config['events'])->toHaveKey('user')
        ->and($config['events'])->toHaveKey('order');
});
