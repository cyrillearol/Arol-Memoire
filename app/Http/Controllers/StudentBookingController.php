<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentBookingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->role === 'etudiant', 403);

        $bookings = Booking::query()
            ->with(['tutor.tutorProfile', 'payment', 'conversation', 'review'])
            ->where('student_id', $request->user()->id)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->orderBy('scheduled_at')
            ->paginate(10)
            ->through(fn (Booking $booking) => $this->resource($booking));

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'mode' => 'upcoming',
        ]);
    }

    public function history(Request $request): Response
    {
        abort_unless($request->user()->role === 'etudiant', 403);

        $bookings = Booking::query()
            ->with(['tutor.tutorProfile', 'payment', 'conversation', 'review'])
            ->where('student_id', $request->user()->id)
            ->whereIn('status', ['terminee', 'refusee', 'annulee'])
            ->latest('scheduled_at')
            ->paginate(10)
            ->through(fn (Booking $booking) => $this->resource($booking));

        return Inertia::render('Bookings/History', [
            'bookings' => $bookings,
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' || $user->id === $booking->student_id || $user->id === $booking->tutor_id, 403);

        $booking->load(['student', 'tutor.tutorProfile', 'payment', 'conversation', 'review']);

        return Inertia::render('Bookings/Show', [
            'booking' => $this->resource($booking),
            'viewerRole' => $user->role,
        ]);
    }

    private function resource(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'subject' => $booking->subject,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            'scheduled_label' => $booking->scheduled_at?->translatedFormat('d M Y, H:i'),
            'duration_minutes' => $booking->duration_minutes,
            'amount' => (float) $booking->amount,
            'status' => $booking->status,
            'notes' => $booking->notes,
            'payment' => $booking->payment ? [
                'status' => $booking->payment->status,
                'method' => $booking->payment->method,
                'reference' => $booking->payment->transaction_reference,
                'paid_at' => $booking->payment->paid_at?->translatedFormat('d M Y, H:i'),
            ] : null,
            'conversation_id' => $booking->conversation?->id,
            'review' => $booking->review ? [
                'rating' => $booking->review->rating,
                'comment' => $booking->review->comment,
            ] : null,
            'student' => $booking->student ? [
                'id' => $booking->student->id,
                'name' => $booking->student->name,
                'email' => $booking->student->email,
            ] : null,
            'tutor' => $booking->tutor ? [
                'id' => $booking->tutor->id,
                'name' => $booking->tutor->name,
                'email' => $booking->tutor->email,
                'domain' => $booking->tutor->tutorProfile?->domain,
            ] : null,
        ];
    }
}