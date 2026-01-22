<?php

namespace Assetplan\Herald;

use Assetplan\Herald\Connections\ConnectionInterface;
use Assetplan\Herald\Connections\FakeConnection;
use Assetplan\Herald\Connections\RabbitMQConnection;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;

class HeraldManager
{
    private array $config;

    private array $connections = [];

    private static array $handlers = [];

    private bool $isFaking = false;

    private ?FakeConnection $fakeConnection = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        // If faking, return the fake connection
        if ($this->isFaking) {
            return $this->fakeConnection;
        }

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

    /**
     * Enable fake mode for testing.
     * When faked, Herald avoids connecting to RabbitMQ and captures messages in memory.
     */
    public function fake(): void
    {
        $this->isFaking = true;
        $this->fakeConnection = new FakeConnection();
    }

    /**
     * Get all published messages (only available in fake mode).
     */
    public function published(): array
    {
        if (! $this->isFaking || ! $this->fakeConnection) {
            return [];
        }

        return $this->fakeConnection->getMessages();
    }

    /**
     * Assert that a message of the given type was published.
     *
     * @param  string  $type  The message type to check
     * @param  callable|null  $callback  Optional callback to filter messages
     */
    public function assertPublished(string $type, ?callable $callback = null): void
    {
        $messages = $this->published();
        $matchingMessages = array_filter($messages, function (Message $message) use ($type, $callback) {
            if ($message->type !== $type) {
                return false;
            }

            if ($callback !== null) {
                return $callback($message);
            }

            return true;
        });

        PHPUnit::assertTrue(
            count($matchingMessages) > 0,
            "Failed asserting that a message of type [{$type}] was published."
        );
    }

    /**
     * Assert that a message of the given type was published a specific number of times.
     *
     * @param  string  $type  The message type to check
     * @param  int  $count  The expected number of times
     */
    public function assertPublishedTimes(string $type, int $count): void
    {
        $messages = $this->published();
        $matchingMessages = array_filter($messages, function (Message $message) use ($type) {
            return $message->type === $type;
        });

        $actualCount = count($matchingMessages);

        PHPUnit::assertSame(
            $count,
            $actualCount,
            "Expected message type [{$type}] to be published {$count} times, but it was published {$actualCount} times."
        );
    }

    /**
     * Assert that no messages were published.
     */
    public function assertNothingPublished(): void
    {
        $count = count($this->published());

        PHPUnit::assertSame(
            0,
            $count,
            "Expected no messages to be published, but {$count} messages were published."
        );
    }
}
