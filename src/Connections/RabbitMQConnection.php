<?php

namespace Assetplan\Herald\Connections;

use Assetplan\Herald\Message;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQConnection implements ConnectionInterface
{
    private AMQPStreamConnection $connection;

    private $channel;

    private ?AMQPMessage $currentMessage = null;

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

    public function consume(): ?Message
    {
        $this->currentMessage = null;

        $callback = function (AMQPMessage $msg) {
            $this->currentMessage = $msg;
        };

        $this->channel->basic_consume(
            $this->channel->queue_bind_ok ?? $this->getQueueName(),
            '',     // consumer_tag
            false,  // no_local
            false,  // no_ack (manual ack)
            false,  // exclusive
            false,  // nowait
            $callback
        );

        // Wait for one message with timeout
        $this->channel->wait(null, false, 1);

        if (! $this->currentMessage) {
            return null;
        }

        $data = json_decode($this->currentMessage->getBody(), true);

        if (! isset($data['type']) || ! isset($data['payload'])) {
            return null;
        }

        return new Message(
            id: $this->currentMessage->getDeliveryTag(),
            type: $data['type'],
            payload: $data['payload'],
            raw: $this->currentMessage
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
