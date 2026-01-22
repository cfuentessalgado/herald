<?php

namespace Assetplan\Herald\Connections;

use Assetplan\Herald\Message;

class FakeConnection implements ConnectionInterface
{
    private array $messages = [];

    public function consume(): ?Message
    {
        // Fake connection doesn't consume messages
        return null;
    }

    public function ack(Message $message): void
    {
        // No-op for fake connection
    }

    public function nack(Message $message, bool $requeue = false): void
    {
        // No-op for fake connection
    }

    public function publish(string $type, array $payload, ?string $id = null): void
    {
        $id = $id ?? uniqid('msg_');

        $this->messages[] = new Message(
            id: $id,
            type: $type,
            payload: $payload,
            raw: null
        );
    }

    public function close(): void
    {
        // No-op for fake connection
    }

    /**
     * Get all published messages.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Clear all published messages.
     */
    public function clearMessages(): void
    {
        $this->messages = [];
    }
}
