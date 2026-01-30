<?php

namespace Assetplan\Herald\Commands;

use Assetplan\Herald\HeraldManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;

class HeraldListCommand extends Command
{
    protected $signature = 'herald:list';

    protected $description = 'List registered Herald event handlers';

    public function handle(HeraldManager $herald): int
    {
        $eventTypes = $herald->getRegisteredEventTypes();

        if (empty($eventTypes)) {
            $this->info('No handlers registered. Use Herald::on() to register handlers.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($eventTypes as $eventType) {
            $handlers = $herald->getHandlers($eventType);

            if (empty($handlers)) {
                $rows[] = [$eventType, '-', '-'];

                continue;
            }

            foreach ($handlers as $handler) {
                [$handlerLabel, $mode] = $this->describeHandler($handler);
                $rows[] = [$eventType, $handlerLabel, $mode];
            }
        }

        $this->table(['Event', 'Handler', 'Mode'], $rows);

        return self::SUCCESS;
    }

    private function describeHandler(string|object|callable $handler): array
    {
        if ($handler instanceof \Closure) {
            return ['Closure', 'sync'];
        }

        if (is_string($handler)) {
            if (! class_exists($handler)) {
                return [$handler, 'missing'];
            }

            $reflection = new \ReflectionClass($handler);

            return [$handler, $reflection->implementsInterface(ShouldQueue::class) ? 'queued' : 'sync'];
        }

        if (is_object($handler)) {
            $class = get_class($handler);

            if ($handler instanceof ShouldQueue) {
                return [$class, 'invalid'];
            }

            return [$class, 'sync'];
        }

        return ['Unknown', 'sync'];
    }
}
