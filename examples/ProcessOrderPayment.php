<?php

namespace App\Herald\Handlers;

use Assetplan\Herald\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued handler example (implements ShouldQueue)
 *
 * Herald dispatches YOUR job directly - no wrapper, no magic.
 * You get full control over queue settings, retries, backoff, etc.
 *
 * Use for heavy operations, API calls, or anything that takes time.
 */
class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Configure queue settings - these are YOUR settings
     */
    public $queue = 'payments';

    public $tries = 3;

    public $backoff = [60, 120, 300]; // Retry after 1min, 2min, 5min

    /**
     * Queued handlers receive the Message in the constructor
     * Herald will call: ProcessOrderPayment::dispatch($message)
     */
    public function __construct(
        public readonly Message $message
    ) {}

    /**
     * Handle the job when it's processed from the queue
     */
    public function handle(): void
    {
        $orderId = $this->message->payload['order_id'];
        $amount = $this->message->payload['amount'];

        // Heavy operation - will be queued automatically by Herald
        $this->processPayment($orderId, $amount);
    }

    private function processPayment(int $orderId, float $amount): void
    {
        // Call payment gateway API
        // Update database
        // Send notifications
    }
}
