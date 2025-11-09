<?php

namespace Assetplan\Herald\Commands;

use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
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

        // Also get legacy config-based event mappings for backward compatibility
        $eventMappings = $herald->getEventsByTopic($topic);

        if (empty($registeredTypes) && empty($eventMappings)) {
            $this->error("No handlers registered for topic: {$topic}");
            $this->comment('Use Herald::on() to register handlers or configure events in config/herald.php');

            return self::FAILURE;
        }

        $this->setupSignalHandlers();

        $connection = $herald->connection($connectionName);

        $this->info('Listening for messages...');

        while (! $this->shouldQuit) {
            try {
                $message = $connection->consume();

                if (! $message) {
                    continue;
                }

                $this->processMessage($message, $connection, $herald, $eventMappings);

            } catch (AMQPTimeoutException $e) {
                // Timeouts are expected when no messages are available
                // Only log in verbose mode
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
        HeraldManager $herald,
        array $eventMappings
    ): void {
        try {
            $this->line("Received message: {$message->type}");

            // Check for Herald::on() registered handlers first
            $handlers = $herald->getHandlers($message->type);

            if (! empty($handlers)) {
                $connection->ack($message);
                $this->executeHandlers($handlers, $message);

                return;
            }

            // Fallback to legacy config-based event dispatching
            if (! isset($eventMappings[$message->type])) {
                $this->comment("Skipping message type: {$message->type} (no handlers registered)");
                $connection->ack($message);

                return;
            }

            $eventClass = $eventMappings[$message->type];

            if (! class_exists($eventClass)) {
                $this->warn("Event class does not exist: {$eventClass}");
                $connection->ack($message);

                return;
            }

            $connection->ack($message);

            Event::dispatch(new $eventClass($message->payload));

            $this->info("Dispatched event: {$eventClass}");

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

        // Handle class strings - resolve and check for ShouldQueue
        if (is_string($handler)) {
            if (! class_exists($handler)) {
                $this->warn("Handler class does not exist: {$handler}");

                return;
            }

            // Check if handler implements ShouldQueue
            $reflection = new \ReflectionClass($handler);
            if ($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
                // Queue the handler
                dispatch(new \Assetplan\Herald\Jobs\HandleHeraldMessage($handler, $message));
                $this->info("Queued handler: {$handler} for: {$message->type}");

                return;
            }

            // Execute synchronously
            $instance = app($handler);
            $instance->handle($message);
            $this->info("Executed handler: {$handler} for: {$message->type}");

            return;
        }

        // Handle object instances - check for ShouldQueue
        if (is_object($handler)) {
            if ($handler instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
                // Queue the handler instance
                dispatch(new \Assetplan\Herald\Jobs\HandleHeraldMessage($handler, $message));
                $this->info('Queued handler instance: '.get_class($handler)." for: {$message->type}");

                return;
            }

            // Execute synchronously
            $handler->handle($message);
            $this->info('Executed handler instance: '.get_class($handler)." for: {$message->type}");

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
