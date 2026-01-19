<?php

namespace Assetplan\Herald\Tests\Unit;

use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;
use Assetplan\Herald\Tests\Fixtures\QueuedHandler;
use Assetplan\Herald\Tests\Fixtures\SyncHandler;
use Assetplan\Herald\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class HandlerExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Herald::clearHandlers();
        Queue::fake();
    }

    public function test_queued_handler_is_dispatched_as_job(): void
    {
        Herald::on('test.event', QueuedHandler::class);

        $message = new Message(
            id: '123',
            type: 'test.event',
            payload: ['foo' => 'bar'],
            raw: null
        );

        // Simulate what HeraldWorkCommand does
        $handlers = app('herald')->getHandlers('test.event');
        $this->assertCount(1, $handlers);

        // Verify handler is a class string
        $handler = $handlers[0];
        $this->assertIsString($handler);
        $this->assertEquals(QueuedHandler::class, $handler);

        // Verify handler implements ShouldQueue
        $reflection = new \ReflectionClass($handler);
        $this->assertTrue($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class));

        // Dispatch the handler job directly
        QueuedHandler::dispatch($message);

        // Assert job was queued
        Queue::assertPushed(QueuedHandler::class, function ($job) use ($message) {
            return $job->message->id === $message->id &&
                   $job->message->type === $message->type &&
                   $job->message->payload === $message->payload;
        });
    }

    public function test_sync_handler_can_be_executed_immediately(): void
    {
        Herald::on('test.event', SyncHandler::class);

        $message = new Message(
            id: '123',
            type: 'test.event',
            payload: ['foo' => 'bar'],
            raw: null
        );

        // Get handlers
        $handlers = app('herald')->getHandlers('test.event');
        $this->assertCount(1, $handlers);

        // Verify handler is a class string
        $handler = $handlers[0];
        $this->assertIsString($handler);

        // Verify handler does NOT implement ShouldQueue
        $reflection = new \ReflectionClass($handler);
        $this->assertFalse($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class));

        // Execute synchronously
        $instance = app($handler);
        $this->assertTrue(method_exists($instance, 'handle'));

        // This should not throw
        $instance->handle($message);

        // Verify no jobs were queued
        Queue::assertNothingPushed();
    }

    public function test_closure_handler_is_executed_synchronously(): void
    {
        $executed = false;

        Herald::on('test.event', function (Message $msg) use (&$executed) {
            $executed = true;
        });

        $message = new Message(
            id: '123',
            type: 'test.event',
            payload: ['foo' => 'bar'],
            raw: null
        );

        // Get handlers
        $handlers = app('herald')->getHandlers('test.event');
        $this->assertCount(1, $handlers);

        // Verify handler is a closure
        $handler = $handlers[0];
        $this->assertInstanceOf(\Closure::class, $handler);

        // Execute the closure
        $handler($message);

        $this->assertTrue($executed);

        // Verify no jobs were queued
        Queue::assertNothingPushed();
    }

    public function test_queued_handler_preserves_message_data(): void
    {
        $message = new Message(
            id: 'msg-456',
            type: 'order.created',
            payload: [
                'order_id' => 789,
                'total' => 99.99,
                'customer' => 'John Doe',
            ],
            raw: ['some' => 'raw data']
        );

        QueuedHandler::dispatch($message);

        Queue::assertPushed(QueuedHandler::class, function ($job) {
            // Verify all message properties are preserved
            return $job->message->id === 'msg-456' &&
                   $job->message->type === 'order.created' &&
                   $job->message->payload['order_id'] === 789 &&
                   $job->message->payload['total'] === 99.99 &&
                   $job->message->payload['customer'] === 'John Doe' &&
                   $job->message->raw === ['some' => 'raw data'];
        });
    }
}
