<?php

namespace Assetplan\Herald\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Assetplan\Herald\Connections\ConnectionInterface connection(?string $name = null)
 * @method static void on(string $eventType, string|object|callable $handler)
 * @method static array getHandlers(string $eventType)
 * @method static array getRegisteredEventTypes()
 * @method static void clearHandlers()
 * @method static void publish(string $type, array $payload, ?string $id = null, ?string $connection = null)
 * @method static void fake()
 * @method static array published()
 * @method static void assertPublished(string $type, ?callable $callback = null)
 * @method static void assertPublishedTimes(string $type, int $count)
 * @method static void assertNothingPublished()
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
