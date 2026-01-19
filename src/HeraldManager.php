<?php

namespace Assetplan\Herald;

use Assetplan\Herald\Connections\ConnectionInterface;
use Assetplan\Herald\Connections\RabbitMQConnection;
use InvalidArgumentException;

class HeraldManager
{
    private array $config;

    private array $connections = [];

    private static array $handlers = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->config['default'];

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (! isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Connection [{$name}] not configured.");
        }

        $config = $this->config['connections'][$name];

        $this->connections[$name] = $this->createConnection($config);

        return $this->connections[$name];
    }

    private function createConnection(array $config): ConnectionInterface
    {
        $driver = $config['driver'] ?? null;

        return match ($driver) {
            'rabbitmq' => new RabbitMQConnection($config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}]. Only 'rabbitmq' is supported.")
        };
    }

    /**
     * Register a handler for a specific event type.
     *
     * @param  string  $eventType  The message type to handle (e.g., 'user.registered')
     * @param  string|object|callable  $handler  The handler (class string, instance, or closure)
     */
    public function on(string $eventType, string|object|callable $handler): void
    {
        if (! isset(static::$handlers[$eventType])) {
            static::$handlers[$eventType] = [];
        }

        static::$handlers[$eventType][] = $handler;
    }

    /**
     * Get all registered handlers for a specific event type.
     *
     * @param  string  $eventType  The message type
     */
    public function getHandlers(string $eventType): array
    {
        return static::$handlers[$eventType] ?? [];
    }

    /**
     * Get all registered event types.
     */
    public function getRegisteredEventTypes(): array
    {
        return array_keys(static::$handlers);
    }

    /**
     * Clear all registered handlers (useful for testing).
     */
    public function clearHandlers(): void
    {
        static::$handlers = [];
    }

    /**
     * Publish a message to the broker.
     *
     * @param  string  $type  The event type (used as routing key)
     * @param  array  $payload  The message payload
     * @param  string|null  $id  Optional message ID (auto-generated if not provided)
     * @param  string|null  $connection  Optional connection name (uses default if not provided)
     */
    public function publish(string $type, array $payload, ?string $id = null, ?string $connection = null): void
    {
        $this->connection($connection)->publish($type, $payload, $id);
    }
}
