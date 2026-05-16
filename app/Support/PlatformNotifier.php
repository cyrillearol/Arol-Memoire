<?php

namespace App\Support;

use App\Events\PlatformNotificationSent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformNotifier
{
    public static function send(User $user, string $title, string $body, ?string $url = null, string $tone = 'info'): void
    {
        if (! $user->exists) {
            return;
        }

        $id = (string) Str::uuid();
        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tone' => $tone,
        ];

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'platform',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RealtimeBroadcaster::send(new PlatformNotificationSent($user->id, [
            'id' => $id,
            ...$payload,
            'read_at' => null,
            'created_at' => now()->toDateTimeString(),
        ]));
    }

    public static function sendToAdmins(string $title, string $body, ?string $url = null, string $tone = 'warning'): void
    {
        User::query()
            ->where('role', 'admin')
            ->where('status', 'actif')
            ->get()
            ->each(fn (User $admin) => self::send($admin, $title, $body, $url, $tone));
    }

    public static function markAsRead(User $user, string $id): void
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public static function markAllAsRead(User $user): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
