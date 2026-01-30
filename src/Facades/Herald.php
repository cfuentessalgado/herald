<?php

namespace Assetplan\Herald\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Assetplan\Herald\Connections\ConnectionInterface connection(?string $name = null)
 * @method static void on(string $eventType, string|object|callable $handler)
 * @method static void onAny(iterable $eventTypes, string|object|callable $handler)
 * @method static array getHandlers(string $eventType)
 * @method static array getRegisteredEventTypes()
 * @method static void clearHandlers()
 * @method static void publish(string $type, array $payload, ?string $id = null, ?string $connection = null)
 *
 * @see \Assetplan\Herald\HeraldManager
 */
class Herald extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'herald';
    }
}
