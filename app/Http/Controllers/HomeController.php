<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $publicTutors = User::query()
            ->with(['tutorProfile', 'receivedReviews'])
            ->where('role', 'tuteur')
            ->where('status', 'actif')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (User $tutor) => $this->tutorCard($tutor));

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'publicTutors' => $publicTutors,
            'tutorCount' => User::where('role', 'tuteur')->where('status', 'actif')->count(),
            'studentCount' => User::where('role', 'etudiant')->where('status', 'actif')->count(),
            'sessionCount' => Booking::whereIn('status', ['acceptee', 'terminee'])->count(),
        ]);
    }

    private function tutorCard(User $tutor): array
    {
        $profile = $tutor->tutorProfile;
        $reviews = $tutor->receivedReviews;

        return [
            'id' => $tutor->id,
            'name' => $tutor->name,
            'domain' => $profile?->domain,
            'subjects' => $profile?->subjects ?? [],
            'hourly_rate' => $profile ? (float) $profile->hourly_rate : null,
            'rating' => $reviews->count() ? round((float) $reviews->avg('rating'), 1) : null,
            'reviews_count' => $reviews->count(),
        ];
    }
}
