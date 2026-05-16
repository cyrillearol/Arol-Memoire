<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class RealtimeBroadcaster
{
    public static function send(object $event, bool $toOthers = false): void
    {
        try {
            $pendingBroadcast = broadcast($event);

            if ($toOthers) {
                $pendingBroadcast->toOthers();
            }

            unset($pendingBroadcast);
        } catch (Throwable $exception) {
            Log::warning('Diffusion temps réel ignorée.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
