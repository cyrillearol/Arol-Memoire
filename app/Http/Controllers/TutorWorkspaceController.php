<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TutorWorkspaceController extends Controller
{
    public function availabilities(Request $request): Response
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        return Inertia::render('Tutor/Availabilities', [
            'availabilities' => $request->user()->availabilities()->orderBy('weekday')->orderBy('start_time')->get(),
        ]);
    }

    public function requests(Request $request): Response
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        $requests = Booking::query()
            ->with(['student', 'payment'])
            ->where('tutor_id', $request->user()->id)
            ->where('status', 'en_attente')
            ->latest()
            ->paginate(10)
            ->through(fn (Booking $booking) => $this->bookingResource($booking));

        $upcoming = Booking::query()
            ->with(['student', 'conversation'])
            ->where('tutor_id', $request->user()->id)
            ->where('status', 'acceptee')
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingResource($booking));

        return Inertia::render('Tutor/Requests', [
            'requests' => $requests,
            'upcoming' => $upcoming,
        ]);
    }

    public function reviews(Request $request): Response
    {
        abort_unless($request->user()->role === 'tuteur', 403);

        $reviews = Review::query()
            ->with(['student', 'booking'])
            ->where('tutor_id', $request->user()->id)
            ->latest()
            ->paginate(12)
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->diffForHumans(),
                'student' => $review->student?->name,
                'subject' => $review->booking?->subject,
            ]);

        return Inertia::render('Tutor/Reviews', [
            'reviews' => $reviews,
            'averageRating' => round((float) Review::where('tutor_id', $request->user()->id)->avg('rating'), 1),
        ]);
    }

    private function bookingResource(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'subject' => $booking->subject,
            'scheduled_label' => $booking->scheduled_at?->translatedFormat('d M Y, H:i'),
            'duration_minutes' => $booking->duration_minutes,
            'amount' => (float) $booking->amount,
            'status' => $booking->status,
            'notes' => $booking->notes,
            'payment_status' => $booking->payment?->status,
            'conversation_id' => $booking->conversation?->id,
            'student' => $booking->student ? [
                'id' => $booking->student->id,
                'name' => $booking->student->name,
                'email' => $booking->student->email,
            ] : null,
        ];
    }
}