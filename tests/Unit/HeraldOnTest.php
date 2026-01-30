<?php

namespace Assetplan\Herald\Tests\Unit;

use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\HeraldManager;
use Assetplan\Herald\Message;
use Assetplan\Herald\Tests\Fixtures\QueuedHandler;
use Assetplan\Herald\Tests\Fixtures\SyncHandler;
use Assetplan\Herald\Tests\TestCase;

class HeraldOnTest extends TestCase
{
    private HeraldManager $herald;

    protected function setUp(): void
    {
        parent::setUp();
        $this->herald = app('herald');
        // Clear handlers before each test
        Herald::clearHandlers();
    }

    public function test_can_register_handler_with_class_string(): void
    {
        Herald::on('user.created', SyncHandler::class);

        $handlers = $this->herald->getHandlers('user.created');

        $this->assertCount(1, $handlers);
        $this->assertEquals(SyncHandler::class, $handlers[0]);
    }

    public function test_can_register_handler_with_object_instance(): void
    {
        $handler = new SyncHandler;
        Herald::on('user.created', $handler);

        $handlers = $this->herald->getHandlers('user.created');

        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    public function test_can_register_handler_with_closure(): void
    {
        $closure = fn (Message $msg) => null;
        Herald::on('user.created', $closure);

        $handlers = $this->herald->getHandlers('user.created');

        $this->assertCount(1, $handlers);
        $this->assertSame($closure, $handlers[0]);
    }

    public function test_can_register_multiple_handlers_for_same_event(): void
    {
        Herald::on('user.created', SyncHandler::class);
        Herald::on('user.created', QueuedHandler::class);
        Herald::on('user.created', fn ($msg) => null);

        $handlers = $this->herald->getHandlers('user.created');

        $this->assertCount(3, $handlers);
    }

    public function test_can_register_handler_for_multiple_event_types(): void
    {
        Herald::onAny([
            'user.created',
            'order.created',
            'payment.received',
        ], SyncHandler::class);

        $this->assertCount(1, $this->herald->getHandlers('user.created'));
        $this->assertCount(1, $this->herald->getHandlers('order.created'));
        $this->assertCount(1, $this->herald->getHandlers('payment.received'));
    }

    public function test_can_get_all_registered_event_types(): void
    {
        Herald::on('user.created', SyncHandler::class);
        Herald::on('order.created', SyncHandler::class);
        Herald::on('payment.received', SyncHandler::class);

        $types = $this->herald->getRegisteredEventTypes();

        $this->assertCount(3, $types);
        $this->assertContains('user.created', $types);
        $this->assertContains('order.created', $types);
        $this->assertContains('payment.received', $types);
    }

    public function test_on_any_registers_event_types(): void
    {
        Herald::onAny([
            'user.created',
            'order.created',
            'payment.received',
        ], SyncHandler::class);

        $types = $this->herald->getRegisteredEventTypes();

        $this->assertCount(3, $types);
        $this->assertContains('user.created', $types);
        $this->assertContains('order.created', $types);
        $this->assertContains('payment.received', $types);
    }

    public function test_returns_empty_array_for_unregistered_event_type(): void
    {
        $handlers = $this->herald->getHandlers('non.existent');

        $this->assertEmpty($handlers);
    }
}
