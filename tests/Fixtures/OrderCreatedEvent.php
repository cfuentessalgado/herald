<?php

namespace Assetplan\Herald\Tests\Fixtures;

class OrderCreatedEvent
{
    public function __construct(public readonly array $data)
    {
    }
}
