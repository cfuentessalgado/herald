<?php

namespace Assetplan\Herald\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Assetplan\Herald\Connections\ConnectionInterface connection(?string $name = null)
 * @method static string|null getEventClass(string $type)
 * @method static array getEventsByTopic(string $topic)
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
