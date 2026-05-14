<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\User;
use App\Services\KkiapayPaymentVerifier;
use App\Support\PlatformNotifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function create(Request $request, User $tutor): Response
    {
        abort_unless($request->user()->role === 'etudiant', 403);
        abort_unless($tutor->role === 'tuteur' && $tutor->status === 'actif', 404);

        $tutor->load(['tutorProfile', 'availabilities', 'receivedReviews']);

        return Inertia::render('Bookings/Create', [
            'tutor' => [
                'id' => $tutor->id,
                'name' => $tutor->name,
                'domain' => $tutor->tutorProfile?->domain,
                'subjects' => $tutor->tutorProfile?->subjects ?? [],
                'hourly_rate' => (float) ($tutor->tutorProfile?->hourly_rate ?? 0),
                'rating' => $tutor->receivedReviews->count() ? round((float) $tutor->receivedReviews->avg('rating'), 1) : null,
                'reviews_count' => $tutor->receivedReviews->count(),
            ],
            'availabilities' => $tutor->availabilities()->where('is_available', true)->orderBy('weekday')->orderBy('start_time')->get(),
            'availableSlots' => $this->availableSlotsFor($tutor),
            'payment' => [
                'provider' => 'kkiapay',
                'public_key' => config('services.kkiapay.public_key'),
                'sandbox' => (bool) config('services.kkiapay.sandbox'),
            ],
        ]);
    }

    public function store(Request $request, User $tutor): RedirectResponse
    {
        abort_unless($request->user()->role === 'etudiant', 403);
        abort_unless($tutor->role === 'tuteur' && $tutor->status === 'actif', 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', Rule::in([60, 90, 120])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'string', Rule::in(['kkiapay'])],
            'kkiapay_transaction_id' => ['required', 'string', 'max:255'],
        ]);

        $scheduledAt = Carbon::parse($validated['scheduled_at'])->seconds(0);
        $durationMinutes = (int) $validated['duration_minutes'];

        if (! $this->isWithinTutorAvailability($tutor, $scheduledAt, $durationMinutes)) {
            return back()->withErrors(['scheduled_at' => 'Choisissez un créneau publié par ce tuteur.'])->withInput();
        }

        if ($this->hasBookingConflict($tutor->id, $scheduledAt, $durationMinutes, ['en_attente', 'acceptee'])) {
            return back()->withErrors(['scheduled_at' => 'Ce créneau est déjà réservé.'])->withInput();
        }

        $conflict = Booking::query()
            ->where('tutor_id', $tutor->id)
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->exists();

        if ($conflict) {
            return back()->withErrors(['scheduled_at' => 'Ce créneau est déjà réservé.'])->withInput();
        }

        $amount = round((float) ($tutor->tutorProfile?->hourly_rate ?? 0) * ($durationMinutes / 60), 2);
        $serviceFee = round($amount * 0.1, 2);
        $totalAmount = round($amount + $serviceFee, 2);

        if (Payment::query()->where('transaction_reference', $validated['kkiapay_transaction_id'])->where('status', 'valide')->exists()) {
            return back()->withErrors(['payment' => 'Cette transaction Kkiapay a déjà été utilisée.'])->withInput();
        }

        $verifiedPayment = app(KkiapayPaymentVerifier::class)->verify(
            $validated['kkiapay_transaction_id'],
            (int) round($totalAmount)
        );

        $booking = DB::transaction(function () use ($request, $tutor, $validated, $scheduledAt, $durationMinutes, $amount, $totalAmount, $verifiedPayment) {
            $booking = Booking::create([
                'student_id' => $request->user()->id,
                'tutor_id' => $tutor->id,
                'subject' => $validated['subject'],
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'amount' => $verifiedPayment['amount'] ?: $totalAmount,
                'status' => 'en_attente',
                'notes' => $validated['notes'] ?? null,
            ]);

            $booking->payment()->create([
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'method' => $validated['payment_method'],
                'status' => 'valide',
                'transaction_reference' => $verifiedPayment['transaction_id'],
                'paid_at' => now(),
            ]);

            return $booking;
        });

        PlatformNotifier::send(
            $tutor,
            'Nouvelle demande de réservation',
            $request->user()->name.' a réservé une séance de '.$booking->subject.' pour le '.$booking->scheduled_at->translatedFormat('d M Y à H:i').'.',
            route('dashboard'),
            'warning'
        );

        PlatformNotifier::send(
            $request->user(),
            'Paiement enregistré',
            'Votre demande de réservation est transmise au tuteur. Vous serez notifié dès sa réponse.',
            route('dashboard'),
            'info'
        );

        return redirect()->route('dashboard')->with('success', 'Réservation envoyée. Le tuteur doit maintenant confirmer la séance.');
    }

    public function accept(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->tutor_id, 403);
        abort_unless($booking->status === 'en_attente', 422);

        if ($this->hasBookingConflict($booking->tutor_id, $booking->scheduled_at, $booking->duration_minutes, ['acceptee'], $booking->id)) {
            return back()->withErrors(['booking' => 'Un autre cours est déjà confirmé sur ce créneau.']);
        }

        $conflict = Booking::query()
            ->where('id', '!=', $booking->id)
            ->where('tutor_id', $booking->tutor_id)
            ->where('scheduled_at', $booking->scheduled_at)
            ->where('status', 'acceptee')
            ->exists();

        if ($conflict) {
            return back()->withErrors(['booking' => 'Un autre cours est déjà confirmé sur ce créneau.']);
        }

        $conversation = DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'acceptee']);

            return Conversation::firstOrCreate([
                'booking_id' => $booking->id,
                'student_id' => $booking->student_id,
                'tutor_id' => $booking->tutor_id,
            ]);
        });

        $booking->load(['student', 'tutor']);

        PlatformNotifier::send(
            $booking->student,
            'Réservation acceptée',
            $booking->tutor->name.' a accepté votre séance de '.$booking->subject.'. La messagerie est ouverte.',
            route('messages.index', $conversation),
            'success'
        );

        return back()->with('success', 'Réservation acceptée. La messagerie est disponible pour cette séance.');
    }

    public function refuse(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->tutor_id, 403);
        abort_unless($booking->status === 'en_attente', 422);

        $booking->update(['status' => 'refusee']);
        $booking->load(['student', 'tutor']);

        PlatformNotifier::send(
            $booking->student,
            'Réservation refusée',
            $booking->tutor->name.' a refusé votre demande de séance de '.$booking->subject.'.',
            route('dashboard'),
            'danger'
        );

        return back()->with('success', 'Réservation refusée.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' || $user->id === $booking->student_id || $user->id === $booking->tutor_id, 403);

        $booking->update(['status' => 'annulee']);
        $booking->load(['student', 'tutor']);

        $recipient = $user->id === $booking->student_id ? $booking->tutor : $booking->student;
        PlatformNotifier::send(
            $recipient,
            'Réservation annulée',
            'La réservation de '.$booking->subject.' a été annulée.',
            route('dashboard'),
            'danger'
        );

        return back()->with('success', 'Réservation annulée.');
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->tutor_id, 403);
        abort_unless($booking->status === 'acceptee', 422);

        $booking->update(['status' => 'terminee']);
        $booking->load('student');

        PlatformNotifier::send(
            $booking->student,
            'Séance terminée',
            'Votre séance de '.$booking->subject.' est terminée. Vous pouvez laisser un avis au tuteur.',
            route('dashboard'),
            'success'
        );

        return back()->with('success', 'Séance marquée comme terminée.');
    }

    private function availableSlotsFor(User $tutor, int $days = 30): array
    {
        $durations = [60, 90, 120];
        $now = now()->seconds(0);
        $availabilities = $tutor->availabilities()
            ->where('is_available', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy('weekday');

        $bookings = Booking::query()
            ->where('tutor_id', $tutor->id)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->where('scheduled_at', '>=', $now->copy()->startOfDay())
            ->where('scheduled_at', '<=', $now->copy()->addDays($days)->endOfDay())
            ->get();

        $slots = [];

        for ($offset = 0; $offset <= $days; $offset++) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $dayAvailabilities = $availabilities->get($date->isoWeekday(), collect());

            foreach ($dayAvailabilities as $availability) {
                $windowStart = Carbon::parse($date->toDateString().' '.$availability->start_time)->seconds(0);
                $windowEnd = Carbon::parse($date->toDateString().' '.$availability->end_time)->seconds(0);
                $slotStart = $windowStart->copy();

                while ($slotStart->copy()->addMinutes(60)->lte($windowEnd)) {
                    if ($slotStart->gt($now)) {
                        $availableDurations = collect($durations)
                            ->filter(function (int $duration) use ($slotStart, $windowEnd, $bookings) {
                                $slotEnd = $slotStart->copy()->addMinutes($duration);

                                return $slotEnd->lte($windowEnd) && ! $bookings->contains(fn (Booking $booking) => $this->bookingOverlaps($booking, $slotStart, $slotEnd));
                            })
                            ->values()
                            ->all();

                        if ($availableDurations !== []) {
                            $slots[] = [
                                'scheduled_at' => $slotStart->format('Y-m-d H:i:s'),
                                'date' => $slotStart->toDateString(),
                                'date_label' => $slotStart->translatedFormat('d M Y'),
                                'weekday_label' => ucfirst($slotStart->translatedFormat('l')),
                                'time' => $slotStart->format('H:i'),
                                'available_until' => $windowEnd->format('H:i'),
                                'available_durations' => $availableDurations,
                            ];
                        }
                    }

                    $slotStart->addMinutes(30);
                }
            }
        }

        return $slots;
    }

    private function isWithinTutorAvailability(User $tutor, Carbon $slotStart, int $durationMinutes): bool
    {
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

        return $tutor->availabilities()
            ->where('is_available', true)
            ->where('weekday', $slotStart->isoWeekday())
            ->get()
            ->contains(function ($availability) use ($slotStart, $slotEnd) {
                $windowStart = Carbon::parse($slotStart->toDateString().' '.$availability->start_time)->seconds(0);
                $windowEnd = Carbon::parse($slotStart->toDateString().' '.$availability->end_time)->seconds(0);

                return $slotStart->gte($windowStart) && $slotEnd->lte($windowEnd);
            });
    }

    private function hasBookingConflict(int $tutorId, Carbon $slotStart, int $durationMinutes, array $statuses, ?int $ignoreBookingId = null): bool
    {
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

        return Booking::query()
            ->where('tutor_id', $tutorId)
            ->whereIn('status', $statuses)
            ->where('scheduled_at', '<', $slotEnd)
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->get()
            ->contains(fn (Booking $booking) => $this->bookingOverlaps($booking, $slotStart, $slotEnd));
    }

    private function bookingOverlaps(Booking $booking, Carbon $slotStart, Carbon $slotEnd): bool
    {
        $bookingStart = $booking->scheduled_at;
        $bookingEnd = $bookingStart->copy()->addMinutes($booking->duration_minutes);

        return $bookingStart->lt($slotEnd) && $bookingEnd->gt($slotStart);
    }
}
