<?php

namespace Assetplan\Herald\Tests\Fixtures;

class UserUpdatedEvent
{
    public function __construct(public readonly array $data)
    {
    }
}
