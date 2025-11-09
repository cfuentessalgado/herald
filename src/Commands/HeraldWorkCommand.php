<?php

namespace Assetplan\Herald\Commands;

use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Message;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class HeraldWorkCommand extends Command
{
    protected $signature = 'herald:work 
                            {topic : The topic to consume (e.g., user, order, or * for all)}
                            {--connection= : The connection to use}';

    protected $description = 'Consume messages from Herald and dispatch Laravel events';

    private bool $shouldQuit = false;

    public function handle(HeraldManager $herald): int
    {
        $topic = $this->argument('topic');
        $connectionName = $this->option('connection');

        $this->info("Starting Herald worker for topic: {$topic}");

        // Get registered event types from Herald::on() calls
        $registeredTypes = $herald->getRegisteredEventTypes();

        if (empty($registeredTypes)) {
            $this->error("No handlers registered for topic: {$topic}");
            $this->comment('Use Herald::on() to register handlers in your service provider');

            return self::FAILURE;
        }

        $this->setupSignalHandlers();

        $connection = $herald->connection($connectionName);

        // Bind queue to topic pattern for RabbitMQ connections
        if (method_exists($connection, 'bindToTopic')) {
            $connection->bindToTopic($topic);
            $this->info("Bound queue to topic pattern: {$topic}");
        }

        $this->info('Listening for messages...');

        // Simple polling loop
        while (! $this->shouldQuit) {
            // Dispatch pending signals (required for SIGINT/SIGTERM to work)
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            try {
                $message = $connection->consume();

                if (! $message) {
                    usleep(100000); // 100ms
                    continue;
                }

                $this->processMessage($message, $connection, $herald);

            } catch (AMQPTimeoutException $e) {
                if ($this->output->isVeryVerbose()) {
                    $this->line('Waiting for messages... (timeout)');
                }
            } catch (\Throwable $e) {
                $this->error("Error in consumer loop: {$e->getMessage()}");
                sleep(1);
            }
        }

        $this->info('Shutting down gracefully...');
        $connection->close();

        return self::SUCCESS;
    }

    private function processMessage(
        Message $message,
        $connection,
        HeraldManager $herald
    ): void {
        try {
            $this->line("Received message: {$message->type}");

            $handlers = $herald->getHandlers($message->type);

            if (empty($handlers)) {
                $this->comment("Skipping message type: {$message->type} (no handlers registered)");
                $connection->ack($message);

                return;
            }

            $connection->ack($message);
            $this->executeHandlers($handlers, $message);

        } catch (\Throwable $e) {
            $this->error("Error processing message: {$e->getMessage()}");
        }
    }

    private function executeHandlers(array $handlers, Message $message): void
    {
        foreach ($handlers as $handler) {
            try {
                $this->executeHandler($handler, $message);
            } catch (\Throwable $e) {
                $this->error("Error executing handler: {$e->getMessage()}");
            }
        }
    }

    private function executeHandler(string|object|callable $handler, Message $message): void
    {
        // Handle closures - always execute synchronously
        if ($handler instanceof \Closure) {
            $handler($message);
            $this->info("Executed closure handler for: {$message->type}");

            return;
        }

        // Handle class strings
        if (is_string($handler)) {
            if (! class_exists($handler)) {
                $this->warn("Handler class does not exist: {$handler}");

                return;
            }

            // Check if handler implements ShouldQueue
            $reflection = new \ReflectionClass($handler);
            if ($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
                // Dispatch the handler job itself with the message
                // The handler receives the Message in its constructor
                $handler::dispatch($message);
                $this->info("Dispatched queued handler: {$handler} for: {$message->type}");

                return;
            }

            // Execute synchronously - call handle() method
            $instance = app($handler);
            if (method_exists($instance, 'handle')) {
                $instance->handle($message);
                $this->info("Executed handler: {$handler} for: {$message->type}");
            } else {
                $this->warn("Handler {$handler} does not have a handle() method");
            }

            return;
        }

        // Handle object instances
        if (is_object($handler)) {
            if ($handler instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
                // Cannot dispatch pre-instantiated handlers - they should be class strings
                $this->warn('Queued handlers must be registered as class strings, not instances: '.get_class($handler));
                $this->comment('Use Herald::on("event.type", HandlerClass::class) instead of new HandlerClass()');

                return;
            }

            // Execute synchronously - call handle() method
            if (method_exists($handler, 'handle')) {
                $handler->handle($message);
                $this->info('Executed handler instance: '.get_class($handler)." for: {$message->type}");
            } else {
                $this->warn('Handler instance '.get_class($handler).' does not have a handle() method');
            }

            return;
        }
    }

    private function setupSignalHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->warn('PCNTL extension not available. Signal handling disabled.');

            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->info('Received SIGTERM signal');
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->info('Received SIGINT signal');
            $this->shouldQuit = true;
        });
    }
}
