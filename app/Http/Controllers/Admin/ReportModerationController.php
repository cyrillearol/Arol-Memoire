<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportModerationController extends Controller
{
    public function update(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['ouvert', 'en_cours', 'resolu', 'ferme'])],
        ]);

        $report->update(['status' => $validated['status']]);

        return back()->with('success', 'Signalement mis à jour.');
    }
}
