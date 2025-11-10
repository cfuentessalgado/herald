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

    /**
     * Prepare the message for serialization (e.g., when queuing).
     * Exclude the raw property as it may contain non-serializable objects.
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }

    /**
     * Restore the message after unserialization.
     * The raw property will be null after unserialization.
     */
    public function __unserialize(array $data): void
    {
        $this->__construct(
            id: $data['id'],
            type: $data['type'],
            payload: $data['payload'],
            raw: null
        );
    }
}
