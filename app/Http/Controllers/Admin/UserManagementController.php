<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function tutors(Request $request): Response
    {
        $this->ensureAdmin($request);

        $status = $request->query('status');

        $query = User::query()
            ->with(['tutorProfile.documents', 'receivedReviews'])
            ->where('role', 'tuteur')
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/Tutors', [
            'users' => $query->paginate(12)->through(fn (User $user) => $this->userResource($user)),
            'filters' => ['status' => $status],
        ]);
    }

    public function students(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Admin/Students', [
            'users' => User::query()
                ->where('role', 'etudiant')
                ->latest()
                ->paginate(12)
                ->through(fn (User $user) => $this->userResource($user)),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $this->ensureAdmin($request);

        $user->load(['tutorProfile.documents', 'studentBookings.tutor', 'tutorBookings.student', 'receivedReviews.student']);

        return Inertia::render('Admin/UserShow', [
            'userItem' => $this->userResource($user),
            'studentBookings' => $user->studentBookings->map(fn ($booking) => [
                'id' => $booking->id,
                'subject' => $booking->subject,
                'status' => $booking->status,
                'scheduled_label' => $booking->scheduled_at?->translatedFormat('d M Y, H:i'),
                'other' => $booking->tutor?->name,
            ])->values(),
            'tutorBookings' => $user->tutorBookings->map(fn ($booking) => [
                'id' => $booking->id,
                'subject' => $booking->subject,
                'status' => $booking->status,
                'scheduled_label' => $booking->scheduled_at?->translatedFormat('d M Y, H:i'),
                'other' => $booking->student?->name,
            ])->values(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_if($user->role === 'admin', 403);

        $user->update(['status' => 'suspendu']);

        PlatformNotifier::send($user, 'Compte suspendu', 'Votre compte a été suspendu par l’administration.', route('dashboard'), 'danger');

        return back()->with('success', 'Compte suspendu.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_if($user->role === 'admin', 403);

        $user->update(['status' => 'actif']);

        PlatformNotifier::send($user, 'Compte activé', 'Votre compte est actif.', route('dashboard'), 'success');

        return back()->with('success', 'Compte activé.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function userResource(User $user): array
    {
        $reviews = $user->receivedReviews;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'created_at' => $user->created_at?->translatedFormat('d M Y'),
            'domain' => $user->tutorProfile?->domain,
            'subjects' => $user->tutorProfile?->subjects ?? [],
            'hourly_rate' => $user->tutorProfile ? (float) $user->tutorProfile->hourly_rate : null,
            'documents' => $user->tutorProfile?->documents->map(fn ($document) => [
                'id' => $document->id,
                'name' => $document->original_name,
            ])->values() ?? [],
            'rating' => $reviews->count() ? round((float) $reviews->avg('rating'), 1) : null,
            'reviews_count' => $reviews->count(),
        ];
    }
}