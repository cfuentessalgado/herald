<?php

namespace Assetplan\Herald\Commands;

use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

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
        
        $eventMappings = $herald->getEventsByTopic($topic);
        
        if (empty($eventMappings)) {
            $this->error("No event mappings found for topic: {$topic}");
            return self::FAILURE;
        }
        
        $this->setupSignalHandlers();
        
        $connection = $herald->connection($connectionName);
        
        $this->info('Listening for messages...');
        
        while (!$this->shouldQuit) {
            try {
                $message = $connection->consume();
                
                if (!$message) {
                    continue;
                }
                
                $this->processMessage($message, $connection, $herald, $eventMappings);
                
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
            
            if (!isset($eventMappings[$message->type])) {
                $this->comment("Skipping message type: {$message->type} (not in topic mappings)");
                $connection->ack($message);
                return;
            }
            
            $eventClass = $eventMappings[$message->type];
            
            if (!class_exists($eventClass)) {
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

    private function setupSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
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
