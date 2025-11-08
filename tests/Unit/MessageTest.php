<?php

use Assetplan\Herald\Message;

it('creates a message with all properties', function () {
    $message = new Message(
        id: '123',
        type: 'user.created',
        payload: ['user_id' => 1, 'email' => 'test@example.com'],
        raw: 'raw-data'
    );

    expect($message->id)->toBe('123')
        ->and($message->type)->toBe('user.created')
        ->and($message->payload)->toBe(['user_id' => 1, 'email' => 'test@example.com'])
        ->and($message->raw)->toBe('raw-data');
});

it('has readonly properties', function () {
    $message = new Message(
        id: '123',
        type: 'user.created',
        payload: ['user_id' => 1],
        raw: 'raw-data'
    );

    expect(fn() => $message->id = '456')
        ->toThrow(\Error::class);
});
