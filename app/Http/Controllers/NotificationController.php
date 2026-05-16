<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->latest('created_at')
            ->paginate(15)
            ->through(function ($notification) {
                $data = json_decode($notification->data ?? '{}', true) ?: [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Notification',
                    'body' => $data['body'] ?? '',
                    'url' => $data['url'] ?? null,
                    'tone' => $data['tone'] ?? 'info',
                    'call' => $data['call'] ?? null,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

        return Inertia::render('Notifications/Index', [
            'items' => $notifications,
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        PlatformNotifier::markAsRead($request->user(), $notification);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        PlatformNotifier::markAllAsRead($request->user());

        return back();
    }
}