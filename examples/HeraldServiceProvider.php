<?php

namespace App\Providers;

use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class HeraldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Example 1: Dispatch to Laravel event
        Herald::on('user.registered', \App\Events\UserRegistered::class);

        // Example 2: Queue a job (implements ShouldQueue)
        Herald::on('order.created', \App\Jobs\ProcessOrder::class);

        // Example 3: Inline closure for simple operations (always sync)
        Herald::on('user.logout', fn (Message $msg) => Log::info("User logged out: {$msg->id}"));

        // Example 4: Pre-configured handler instance
        $emailSender = new \App\Services\EmailSender(
            apiKey: config('services.sendgrid.key'),
            fromAddress: 'noreply@example.com'
        );
        Herald::on('email.send', $emailSender);

        // Example 5: Multiple handlers for same event
        Herald::on('payment.received', \App\Jobs\SendReceipt::class);
        Herald::on('payment.received', \App\Jobs\UpdateInventory::class);
        Herald::on('payment.received', fn ($msg) => cache()->forget("order.{$msg->payload['order_id']}"));
    }
}
