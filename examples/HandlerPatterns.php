<?php

/**
 * Herald Handler Patterns - Complete Guide
 *
 * This file demonstrates the three main patterns for handling Herald messages:
 * 1. Queued handlers (ShouldQueue) - Herald dispatches YOUR job
 * 2. Sync handlers (handle method) - Fast, immediate execution
 * 3. Closures - Inline logic or adapter pattern for legacy jobs
 */

namespace App\Providers;

use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;
use Illuminate\Support\ServiceProvider;

class HeraldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ============================================
        // PATTERN 1: QUEUED HANDLERS (SHOULDQUEUE)
        // ============================================

        // Herald calls: ProcessOrderPayment::dispatch($message)
        // You get full control - YOUR job, YOUR queue settings, YOUR retries
        Herald::on('order.created', \App\Jobs\ProcessOrderPayment::class);

        /*
         * Example job structure:
         *
         * class ProcessOrderPayment implements ShouldQueue
         * {
         *     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
         *
         *     public $queue = 'payments';      // YOUR queue name
         *     public $tries = 3;               // YOUR retry logic
         *     public $backoff = [60, 120];     // YOUR backoff strategy
         *
         *     public function __construct(
         *         public readonly Message $message  // Herald delivers the Message
         *     ) {}
         *
         *     public function handle(): void
         *     {
         *         // Process the message
         *         $orderId = $this->message->payload['order_id'];
         *         // ...
         *     }
         * }
         */

        // ============================================
        // PATTERN 2: SYNC HANDLERS (NO SHOULDQUEUE)
        // ============================================

        // Fast operations that should execute immediately
        Herald::on('cache.invalidate', \App\Handlers\InvalidateCache::class);

        /*
         * Example sync handler:
         *
         * class InvalidateCache
         * {
         *     public function handle(Message $message): void
         *     {
         *         // Fast operation - runs immediately
         *         cache()->forget($message->payload['key']);
         *     }
         * }
         */

        // ============================================
        // PATTERN 3: CLOSURES
        // ============================================

        // A) Inline Logic - Simple operations
        Herald::on('metrics.track', function (Message $msg) {
            \Illuminate\Support\Facades\Log::info('Metric tracked', [
                'metric' => $msg->payload['name'],
                'value' => $msg->payload['value'],
            ]);
        });

        // B) Adapter Pattern - Bridge to legacy jobs that don't know about Herald
        //    This is the "chef's kiss" pattern - makes the boundary crystal clear!
        Herald::on('user.registered', function (Message $msg) {
            // Your existing job, YOUR way
            \App\Jobs\SendWelcomeEmail::dispatch(
                userId: $msg->payload['user_id'],
                email: $msg->payload['email'],
                name: $msg->payload['name']
            );
        });

        // C) Multiple actions for one event
        Herald::on('order.cancelled', function (Message $msg) {
            // Immediate cache cleanup
            cache()->forget("order.{$msg->payload['order_id']}");

            // Then dispatch heavy operations
            \App\Jobs\ProcessRefund::dispatch($msg->payload['order_id']);
            \App\Jobs\RestockInventory::dispatch($msg->payload['items']);
        });

        // ============================================
        // WHY THIS APPROACH IS CLEAN
        // ============================================

        /*
         * 1. Clear Contract
         *    Herald delivers Message, period. What you do with it is your business.
         *
         * 2. No Magic
         *    We don't try to be smart about unwrapping or adapting.
         *    What you see is what you get.
         *
         * 3. Explicit Adapters
         *    Want to use old jobs? Write a closure adapter.
         *    It's obvious what's happening - no hidden wrappers.
         *
         * 4. Proper Queue Settings
         *    Jobs dispatch with THEIR OWN settings:
         *    - queue name
         *    - tries
         *    - backoff
         *    - timeout
         *    - middleware
         *    Herald doesn't interfere.
         *
         * 5. No Double-Wrapping
         *    Herald dispatches YOUR job directly, not a wrapper job.
         *    Your job shows up in Horizon/queue logs as itself.
         */
    }
}
