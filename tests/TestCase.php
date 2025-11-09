<?php

namespace Assetplan\Herald\Tests;

use Assetplan\Herald\HeraldServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            HeraldServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('herald.default', 'rabbitmq');
        $app['config']->set('herald.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'exchange' => 'herald-test',
            'exchange_type' => 'topic',
            'queue' => 'test-queue',
            'queue_durable' => true,
        ]);
    }
}
