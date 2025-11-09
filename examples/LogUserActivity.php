<?php

namespace App\Herald\Handlers;

use Assetplan\Herald\Message;
use Illuminate\Support\Facades\Log;

/**
 * Synchronous handler example (no ShouldQueue interface)
 * Use for fast operations that complete quickly
 */
class LogUserActivity
{
    public function handle(Message $message): void
    {
        Log::info('User activity', [
            'event_id' => $message->id,
            'event_type' => $message->type,
            'data' => $message->payload,
        ]);
    }
}
