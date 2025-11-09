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
 * Use for heavy operations, API calls, or anything that takes time
 */
class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Configure queue settings
     */
    public $queue = 'payments';

    public $tries = 3;

    public $backoff = [60, 120, 300]; // Retry after 1min, 2min, 5min

    public function handle(Message $message): void
    {
        $orderId = $message->payload['order_id'];
        $amount = $message->payload['amount'];

        // Heavy operation - will be queued automatically
        $this->processPayment($orderId, $amount);
    }

    private function processPayment(int $orderId, float $amount): void
    {
        // Call payment gateway API
        // Update database
        // Send notifications
    }
}
