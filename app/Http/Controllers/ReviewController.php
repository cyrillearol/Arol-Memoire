<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->student_id, 403);
        abort_unless(in_array($booking->status, ['acceptee', 'terminee'], true), 422);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking->review()->updateOrCreate([
            'booking_id' => $booking->id,
        ], [
            'student_id' => $booking->student_id,
            'tutor_id' => $booking->tutor_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Avis enregistré.');
    }
}
