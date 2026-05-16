<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        $validated = $this->validateAvailability($request);

        $request->user()->availabilities()->create([
            ...$validated,
            'is_available' => $request->boolean('is_available', true),
        ]);

        return back()->with('success', 'Disponibilité ajoutée.');
    }

    public function update(Request $request, int $availability): RedirectResponse
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        $validated = $this->validateAvailability($request);

        $request->user()->availabilities()->whereKey($availability)->update([
            ...$validated,
            'is_available' => $request->boolean('is_available', true),
        ]);

        return back()->with('success', 'Disponibilité modifiée.');
    }

    public function destroy(Request $request, int $availability): RedirectResponse
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        $request->user()->availabilities()->whereKey($availability)->delete();

        return back()->with('success', 'Disponibilité supprimée.');
    }

    private function validateAvailability(Request $request): array
    {
        return $request->validate([
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['nullable', 'boolean'],
        ]);
    }
}
