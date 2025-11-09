<?php

namespace Assetplan\Herald\Tests\Fixtures;

class UserCreatedEvent
{
    public function __construct(public readonly array $data) {}
}
