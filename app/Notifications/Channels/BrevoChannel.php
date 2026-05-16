<?php

namespace App\Notifications\Channels;

class BrevoChannel
{
    public function send(object $notifiable, object $notification): void
    {
        if (method_exists($notification, 'toBrevo')) {
            $notification->toBrevo($notifiable);
        }
    }
}
