<?php

namespace Assetplan\Herald\Connections;

use Assetplan\Herald\Message;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQConnection implements ConnectionInterface
{
    private AMQPStreamConnection $connection;

    private $channel;

    private string $queueName;

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost'] ?? '/'
        );

        $this->channel = $this->connection->channel();

        // Store queue name
        $this->queueName = $config['queue'];

        // Declare exchange (idempotent)
        $this->channel->exchange_declare(
            $config['exchange'],
            $config['exchange_type'] ?? 'topic',
            false,  // passive
            true,   // durable
            false   // auto_delete
        );

        // Declare queue with app-specific name
        $this->channel->queue_declare(
            $this->queueName,
            false,  // passive
            $config['queue_durable'] ?? true,  // durable
            false,  // exclusive
            false   // auto_delete
        );

        // Set up basic consume
        $this->channel->basic_qos(
            null,   // prefetch_size
            1,      // prefetch_count
            null    // global
        );
    }

    /**
     * Start consuming messages with a callback (blocking infinite loop).
     * 
     * @param callable $callback Function that receives Message objects
     * @throws \Throwable
     */
    public function startConsuming(callable $callback): void
    {
        $this->channel->basic_consume(
            $this->queueName,
            '',     // consumer_tag
            false,  // no_local
            false,  // no_ack (manual ack required)
            false,  // exclusive
            false,  // nowait
            function (AMQPMessage $msg) use ($callback) {
                $data = json_decode($msg->getBody(), true);

                if (isset($data['type']) && isset($data['payload'])) {
                    $message = new Message(
                        id: $data['id'] ?? $msg->getDeliveryTag(),
                        type: $data['type'],
                        payload: $data['payload'],
                        raw: $msg
                    );
                    
                    $callback($message);
                }
            }
        );

        // Blocking infinite loop - processes messages continuously
        $this->channel->consume();
    }

    public function consume(): ?Message
    {
        // Simple polling - just get one message
        $message = $this->channel->basic_get($this->queueName, false);

        if (! $message) {
            return null;
        }

        $data = json_decode($message->getBody(), true);

        if (! isset($data['type']) || ! isset($data['payload'])) {
            // Invalid message format - ack and skip to avoid reprocessing
            $this->channel->basic_ack($message->getDeliveryTag());
            return null;
        }

        return new Message(
            id: $data['id'] ?? $message->getDeliveryTag(),
            type: $data['type'],
            payload: $data['payload'],
            raw: $message
        );
    }

    public function ack(Message $message): void
    {
        if ($message->raw instanceof AMQPMessage) {
            $this->channel->basic_ack($message->raw->getDeliveryTag());
        }
    }

    public function nack(Message $message, bool $requeue = false): void
    {
        if ($message->raw instanceof AMQPMessage) {
            $this->channel->basic_nack(
                $message->raw->getDeliveryTag(),
                false,  // multiple
                $requeue
            );
        }
    }

    public function bindToTopic(string $routingKey): void
    {
        // Bind queue to exchange with routing key pattern
        $this->channel->queue_bind(
            $this->queueName,
            $this->config['exchange'],
            $routingKey
        );
    }

    public function publish(string $type, array $payload, ?string $id = null): void
    {
        $message = json_encode([
            'id' => $id ?? uniqid('herald_', true),
            'type' => $type,
            'payload' => $payload,
        ]);

        $amqpMessage = new AMQPMessage($message, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        // Use event type as routing key for topic-based routing
        $this->channel->basic_publish(
            $amqpMessage,
            $this->config['exchange'],
            $type  // routing key
        );
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connection->close();
    }

    private function getQueueName(): string
    {
        return $this->queueName;
    }
}
