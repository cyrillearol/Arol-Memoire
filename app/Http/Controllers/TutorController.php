<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TutorController extends Controller
{
    public function index(Request $request): Response
    {
        $subject = trim((string) $request->query('subject'));

        $query = User::query()
            ->with(['tutorProfile', 'receivedReviews'])
            ->where('role', 'tuteur')
            ->where('status', 'actif');

        if ($subject !== '') {
            $query->whereHas('tutorProfile', function ($profileQuery) use ($subject) {
                $profileQuery->where('subjects', 'like', '%'.$subject.'%')
                    ->orWhere('domain', 'like', '%'.$subject.'%');
            });
        }

        $tutors = $query->latest()->paginate(9)->through(fn (User $tutor) => $this->tutorResource($tutor));

        $subjects = User::query()
            ->with('tutorProfile')
            ->where('role', 'tuteur')
            ->where('status', 'actif')
            ->get()
            ->flatMap(fn (User $tutor) => $tutor->tutorProfile?->subjects ?? [])
            ->filter()
            ->unique()
            ->values();

        return Inertia::render('Tutors/Index', [
            'tutors' => $tutors,
            'filters' => ['subject' => $subject],
            'subjects' => $subjects,
        ]);
    }

    public function show(Request $request, User $tutor): Response
    {
        abort_unless($tutor->role === 'tuteur' && $tutor->status === 'actif', 404);

        $tutor->load(['tutorProfile', 'availabilities', 'receivedReviews.student']);

        $canMessage = false;
        if ($request->user()) {
            $canMessage = Booking::query()
                ->where('student_id', $request->user()->id)
                ->where('tutor_id', $tutor->id)
                ->where('status', 'acceptee')
                ->exists();
        }

        return Inertia::render('Tutors/Show', [
            'tutor' => $this->tutorResource($tutor),
            'availabilities' => $tutor->availabilities->map(fn (Availability $availability) => [
                'id' => $availability->id,
                'weekday' => $availability->weekday,
                'start_time' => substr((string) $availability->start_time, 0, 5),
                'end_time' => substr((string) $availability->end_time, 0, 5),
                'is_available' => $availability->is_available,
            ]),
            'reviews' => $tutor->receivedReviews->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'student' => $review->student?->name,
                'created_at' => $review->created_at?->diffForHumans(),
            ]),
            'canMessage' => $canMessage,
        ]);
    }

    private function tutorResource(User $tutor): array
    {
        $profile = $tutor->tutorProfile;
        $reviews = $tutor->receivedReviews;

        return [
            'id' => $tutor->id,
            'name' => $tutor->name,
            'email' => $tutor->email,
            'domain' => $profile?->domain,
            'subjects' => $profile?->subjects ?? [],
            'hourly_rate' => $profile ? (float) $profile->hourly_rate : 0,
            'bio' => $profile?->bio,
            'rating' => $reviews->count() ? round((float) $reviews->avg('rating'), 1) : null,
            'reviews_count' => $reviews->count(),
        ];
    }
}
