<?php

use Assetplan\Herald\Facades\Herald;

beforeEach(function () {
    Herald::fake();
});

it('enables fake mode and captures published messages', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    $messages = Herald::published();

    expect($messages)->toHaveCount(1);
    expect($messages[0]->type)->toBe('user.created');
    expect($messages[0]->payload)->toBe(['user_id' => 123]);
});

it('captures multiple published messages', function () {
    Herald::publish('user.created', ['user_id' => 123]);
    Herald::publish('order.created', ['order_id' => 456]);
    Herald::publish('user.created', ['user_id' => 789]);

    $messages = Herald::published();

    expect($messages)->toHaveCount(3);
});

it('can assert a message was published', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    Herald::assertPublished('user.created');
});

it('can assert a message was published with callback', function () {
    Herald::publish('user.created', ['user_id' => 123, 'email' => 'test@example.com']);
    Herald::publish('user.created', ['user_id' => 456, 'email' => 'other@example.com']);

    Herald::assertPublished('user.created', function ($message) {
        return $message->payload['user_id'] === 123;
    });

    Herald::assertPublished('user.created', function ($message) {
        return $message->payload['email'] === 'other@example.com';
    });
});

it('can assert a message was published specific number of times', function () {
    Herald::publish('user.created', ['user_id' => 123]);
    Herald::publish('user.created', ['user_id' => 456]);
    Herald::publish('order.created', ['order_id' => 789]);

    Herald::assertPublishedTimes('user.created', 2);
    Herald::assertPublishedTimes('order.created', 1);
});

it('can assert nothing was published', function () {
    Herald::assertNothingPublished();
});

it('fails assertion when message was not published', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    expect(fn () => Herald::assertPublished('order.created'))
        ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
});

it('fails assertion when callback does not match', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    expect(fn () => Herald::assertPublished('user.created', fn ($message) => $message->payload['user_id'] === 999))
        ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
});

it('fails assertion when published times do not match', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    expect(fn () => Herald::assertPublishedTimes('user.created', 2))
        ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
});

it('fails assertion when something was published but expected nothing', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    expect(fn () => Herald::assertNothingPublished())
        ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
});

it('does not attempt to connect to rabbitmq when faked', function () {
    // This test verifies that FakeConnection is used instead of RabbitMQConnection
    // If it were trying to connect to RabbitMQ, it would fail or hang
    Herald::publish('user.created', ['user_id' => 123]);
    Herald::publish('order.created', ['order_id' => 456]);

    expect(Herald::published())->toHaveCount(2);
});

it('preserves message id when provided', function () {
    Herald::publish('user.created', ['user_id' => 123], 'custom-id-123');

    $messages = Herald::published();

    expect($messages[0]->id)->toBe('custom-id-123');
});

it('generates message id when not provided', function () {
    Herald::publish('user.created', ['user_id' => 123]);

    $messages = Herald::published();

    expect($messages[0]->id)->not->toBeEmpty();
});

it('published returns empty array when not in fake mode', function () {
    // Create a new instance without faking
    $manager = new \Assetplan\Herald\HeraldManager([
        'default' => 'rabbitmq',
        'connections' => [
            'rabbitmq' => [
                'driver' => 'rabbitmq',
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'exchange' => 'herald-test',
            ],
        ],
    ]);

    expect($manager->published())->toBe([]);
});
