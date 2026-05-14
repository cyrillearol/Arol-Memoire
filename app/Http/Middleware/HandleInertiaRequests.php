<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => fn () => $this->notificationsFor($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private function notificationsFor(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        }

        $query = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id);

        $recent = (clone $query)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function ($notification) {
                $data = json_decode($notification->data ?? '{}', true) ?: [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Notification',
                    'body' => $data['body'] ?? '',
                    'url' => $data['url'] ?? null,
                    'tone' => $data['tone'] ?? 'info',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            })
            ->values();

        return [
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'recent' => $recent,
        ];
    }
}