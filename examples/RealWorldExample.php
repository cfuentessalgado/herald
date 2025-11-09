<?php

/**
 * Complete Real-World Example
 * 
 * This example shows a complete Herald setup for an e-commerce application
 * handling user registrations, orders, and payments across multiple apps.
 */

namespace App\Providers;

use Assetplan\Herald\Facades\Herald;
use Assetplan\Herald\Message;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class HeraldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ============================================
        // USER EVENTS
        // ============================================
        
        // When a user registers in the legacy CakePHP app,
        // dispatch a Laravel event to handle welcome email, profile creation, etc.
        Herald::on('user.registered', \App\Events\UserRegistered::class);
        
        // Quick logging for user logouts (sync - fast operation)
        Herald::on('user.logout', function (Message $msg) {
            Log::info("User logged out", [
                'user_id' => $msg->payload['user_id'],
                'ip' => $msg->payload['ip_address'],
            ]);
        });
        
        // Profile updates trigger cache invalidation (sync - immediate)
        Herald::on('user.profile.updated', \App\Services\CacheInvalidator::class);
        
        
        // ============================================
        // ORDER EVENTS
        // ============================================
        
        // Heavy processing - queue these jobs
        Herald::on('order.created', \App\Jobs\ProcessOrderJob::class);
        Herald::on('order.created', \App\Jobs\NotifyWarehouse::class);
        Herald::on('order.created', \App\Jobs\SendOrderConfirmationEmail::class);
        
        // Order status changes
        Herald::on('order.shipped', \App\Jobs\SendShippingNotification::class);
        Herald::on('order.delivered', \App\Jobs\RequestProductReview::class);
        
        // Order cancellations need multiple actions
        Herald::on('order.cancelled', \App\Jobs\ProcessRefund::class);
        Herald::on('order.cancelled', \App\Jobs\RestockInventory::class);
        Herald::on('order.cancelled', fn (Message $msg) => 
            cache()->forget("order.{$msg->payload['order_id']}")
        );
        
        
        // ============================================
        // PAYMENT EVENTS
        // ============================================
        
        // Critical payment processing - queued with retries
        Herald::on('payment.received', \App\Jobs\ProcessPayment::class);
        Herald::on('payment.received', \App\Jobs\GenerateInvoice::class);
        
        // Payment failures need immediate attention
        Herald::on('payment.failed', \App\Jobs\NotifyPaymentFailure::class);
        Herald::on('payment.failed', \App\Jobs\AlertFinanceTeam::class);
        
        
        // ============================================
        // INVENTORY EVENTS
        // ============================================
        
        // Sync operations for inventory (immediate data consistency)
        Herald::on('inventory.low', \App\Handlers\SendLowStockAlert::class);
        Herald::on('inventory.updated', \App\Handlers\InvalidateProductCache::class);
        
        
        // ============================================
        // ANALYTICS EVENTS
        // ============================================
        
        // Batch analytics to a dedicated queue
        Herald::on('analytics.track', \App\Jobs\TrackAnalytics::class);
        Herald::on('analytics.pageview', \App\Jobs\RecordPageView::class);
        
        
        // ============================================
        // NOTIFICATION EVENTS
        // ============================================
        
        // Email notifications (queued)
        Herald::on('notification.email', \App\Jobs\SendEmail::class);
        
        // SMS notifications (queued to 'sms' queue for rate limiting)
        Herald::on('notification.sms', \App\Jobs\SendSMS::class);
        
        // Push notifications (immediate)
        Herald::on('notification.push', \App\Handlers\SendPushNotification::class);
    }
}
