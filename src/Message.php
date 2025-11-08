<?php

namespace Assetplan\Herald;

class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly mixed $raw
    ) {}
}
