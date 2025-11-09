<?php

namespace Assetplan\Herald\Connections;

use Assetplan\Herald\Message;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Predis\Client;

class RedisConnection implements ConnectionInterface
{
    private Client $redis;

    private string $stream;

    private string $consumerGroup;

    private string $consumerName;

    public function __construct(array $config)
    {
        $this->redis = RedisFacade::connection($config['connection'] ?? 'default')->client();
        $this->stream = $config['stream'];
        $this->consumerGroup = $config['consumer_group'];
        $this->consumerName = $config['consumer_name'];

        // Create consumer group (idempotent with MKSTREAM)
        try {
            $this->redis->xgroup('CREATE', $this->stream, $this->consumerGroup, '0', 'MKSTREAM');
        } catch (\Exception $e) {
            // Group already exists, ignore
        }
    }

    public function consume(): ?Message
    {
        // Read from stream
        $messages = $this->redis->xreadgroup(
            $this->consumerGroup,
            $this->consumerName,
            [$this->stream => '>'],
            1,          // count
            1000        // block ms
        );

        if (empty($messages) || empty($messages[$this->stream])) {
            return null;
        }

        $message = reset($messages[$this->stream]);
        $messageId = key($messages[$this->stream]);

        if (! isset($message['data'])) {
            return null;
        }

        $data = json_decode($message['data'], true);

        if (! isset($data['type']) || ! isset($data['payload'])) {
            return null;
        }

        return new Message(
            id: $messageId,
            type: $data['type'],
            payload: $data['payload'],
            raw: ['id' => $messageId, 'stream' => $this->stream]
        );
    }

    public function ack(Message $message): void
    {
        if (is_array($message->raw) && isset($message->raw['id'])) {
            $this->redis->xack(
                $this->stream,
                $this->consumerGroup,
                [$message->raw['id']]
            );
        }
    }

    public function nack(Message $message, bool $requeue = false): void
    {
        // Redis Streams doesn't have a direct NACK equivalent
        // We can either not ACK (message will be redelivered after timeout)
        // or ACK and optionally republish to a dead letter stream

        if (! $requeue && is_array($message->raw) && isset($message->raw['id'])) {
            // Just ACK it to remove from pending
            $this->ack($message);
        }
        // If requeue is true, don't ACK - it will be redelivered
    }

    public function close(): void
    {
        // Predis client doesn't need explicit closing
    }
}
