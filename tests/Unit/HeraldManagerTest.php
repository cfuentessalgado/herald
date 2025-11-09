<?php

use Assetplan\Herald\HeraldManager;

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
        ],
    ];
});

it('throws exception for unconfigured connection', function () {
    $manager = new HeraldManager($this->config);

    expect(fn () => $manager->connection('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Connection [invalid] not configured.');
});

it('throws exception for unsupported driver', function () {
    $config = $this->config;
    $config['connections']['kafka'] = ['driver' => 'kafka'];

    $manager = new HeraldManager($config);

    expect(fn () => $manager->connection('kafka'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported driver [kafka].');
});
