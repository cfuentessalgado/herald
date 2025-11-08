<?php

namespace Assetplan\Herald;

use Assetplan\Herald\Connections\ConnectionInterface;
use Assetplan\Herald\Connections\RabbitMQConnection;
use Assetplan\Herald\Connections\RedisConnection;
use InvalidArgumentException;

class HeraldManager
{
    private array $config;
    private array $connections = [];
    
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
        
        if (!isset($this->config['connections'][$name])) {
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
            'redis' => new RedisConnection($config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}].")
        };
    }
    
    public function getEventClass(string $type): ?string
    {
        $events = $this->config['events'] ?? [];
        
        foreach ($events as $group => $mappings) {
            if (isset($mappings[$type])) {
                return $mappings[$type];
            }
        }
        
        return null;
    }
    
    public function getEventsByTopic(string $topic): array
    {
        $events = $this->config['events'] ?? [];
        
        if ($topic === '*') {
            // Return all event mappings
            $all = [];
            foreach ($events as $mappings) {
                $all = array_merge($all, $mappings);
            }
            return $all;
        }
        
        return $events[$topic] ?? [];
    }
}
