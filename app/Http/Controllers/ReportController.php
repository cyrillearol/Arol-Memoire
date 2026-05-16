<?php

namespace App\Http\Controllers;

use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reported_user_id' => ['nullable', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:40', 'max:3000'],
        ], [
            'description.min' => 'Ajoutez quelques détails pour que l’administration puisse comprendre le problème.',
        ]);

        $request->user()->reportsSubmitted()->create([
            'reported_user_id' => $validated['reported_user_id'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'ouvert',
        ]);

        PlatformNotifier::sendToAdmins(
            'Nouveau signalement',
            $request->user()->name.' a transmis un signalement : '.$validated['subject'].'.',
            route('admin.reports.index'),
            'warning'
        );

        return back()->with('success', 'Signalement transmis à l’administration.');
    }
}
