<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match ($user->role) {
            'admin' => $this->adminDashboard(),
            'tuteur' => $this->tutorDashboard($user),
            default => $this->studentDashboard($user),
        };
    }

    private function studentDashboard(User $student): Response
    {
        $upcoming = Booking::query()
            ->with(['tutor.tutorProfile', 'payment', 'conversation'])
            ->where('student_id', $student->id)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingCard($booking));

        $recommendedTutors = User::query()
            ->with(['tutorProfile', 'receivedReviews'])
            ->where('role', 'tuteur')
            ->where('status', 'actif')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (User $tutor) => $this->tutorCard($tutor));

        $subjects = $recommendedTutors
            ->flatMap(fn (array $tutor) => $tutor['subjects'])
            ->filter()
            ->unique()
            ->values();

        return Inertia::render('Dashboard/Student', [
            'metrics' => [
                'upcoming' => Booking::where('student_id', $student->id)->whereIn('status', ['en_attente', 'acceptee'])->count(),
                'completed' => Booking::where('student_id', $student->id)->where('status', 'terminee')->count(),
                'favoriteTutors' => $recommendedTutors->count(),
                'unreadMessages' => $this->unreadMessagesFor($student),
            ],
            'upcomingBookings' => $upcoming,
            'recommendedTutors' => $recommendedTutors,
            'subjects' => $subjects,
        ]);
    }

    private function tutorDashboard(User $tutor): Response
    {
        $requests = Booking::query()
            ->with(['student', 'payment'])
            ->where('tutor_id', $tutor->id)
            ->where('status', 'en_attente')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingCard($booking));

        $upcoming = Booking::query()
            ->with(['student', 'conversation'])
            ->where('tutor_id', $tutor->id)
            ->where('status', 'acceptee')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingCard($booking));

        $conversations = Conversation::query()
            ->with(['student', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->where('tutor_id', $tutor->id)
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Conversation $conversation) => $this->conversationCard($conversation, $tutor));

        $reviews = Review::query()
            ->with('student')
            ->where('tutor_id', $tutor->id)
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Review $review) => $this->reviewCard($review));

        return Inertia::render('Dashboard/Tutor', [
            'profile' => $tutor->loadMissing(['tutorProfile.documents', 'availabilities'])->only(['id', 'name', 'email', 'role', 'status']),
            'tutorProfile' => $tutor->tutorProfile,
            'documents' => $tutor->tutorProfile?->documents ?? [],
            'availabilities' => $tutor->availabilities()->orderBy('weekday')->orderBy('start_time')->get(),
            'metrics' => [
                'requests' => Booking::where('tutor_id', $tutor->id)->where('status', 'en_attente')->count(),
                'upcoming' => Booking::where('tutor_id', $tutor->id)->where('status', 'acceptee')->count(),
                'monthRevenue' => (float) Booking::where('tutor_id', $tutor->id)->whereIn('status', ['acceptee', 'terminee'])->whereMonth('created_at', now()->month)->sum('amount'),
                'averageRating' => round((float) Review::where('tutor_id', $tutor->id)->avg('rating'), 1),
            ],
            'requests' => $requests,
            'upcomingBookings' => $upcoming,
            'recentMessages' => $conversations,
            'reviews' => $reviews,
        ]);
    }

    private function adminDashboard(): Response
    {
        $pendingTutors = User::query()
            ->with(['tutorProfile.documents'])
            ->where('role', 'tuteur')
            ->where('status', 'en_attente')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (User $tutor) => [
                ...$this->tutorCard($tutor),
                'email' => $tutor->email,
                'status' => $tutor->status,
                'created_at' => $tutor->created_at?->toDateString(),
                'documents' => $tutor->tutorProfile?->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                ])->values() ?? [],
            ]);

        $reports = Report::query()
            ->with(['reporter', 'reportedUser'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'subject' => $report->subject,
                'description' => $report->description,
                'status' => $report->status,
                'created_at' => $report->created_at?->diffForHumans(),
                'reporter' => $report->reporter?->name,
                'reported_user' => $report->reportedUser?->name,
            ]);

        $subjectStats = Booking::query()
            ->select('subject', DB::raw('count(*) as total'))
            ->groupBy('subject')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Admin', [
            'stats' => [
                'students' => User::where('role', 'etudiant')->count(),
                'validatedTutors' => User::where('role', 'tuteur')->where('status', 'actif')->count(),
                'bookings' => Booking::count(),
                'pendingReports' => Report::where('status', 'ouvert')->count(),
                'pendingTutors' => $pendingTutors->count(),
            ],
            'pendingTutors' => $pendingTutors,
            'reports' => $reports,
            'subjectStats' => $subjectStats,
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
            'hourly_rate' => $profile ? (float) $profile->hourly_rate : 0,
            'bio' => $profile?->bio,
            'rating' => $reviews->count() ? round((float) $reviews->avg('rating'), 1) : null,
            'reviews_count' => $reviews->count(),
        ];
    }

    private function bookingCard(Booking $booking): array
    {
        $other = $booking->relationLoaded('tutor') && $booking->tutor ? $booking->tutor : $booking->student;
        $profile = $booking->tutor?->tutorProfile;

        return [
            'id' => $booking->id,
            'subject' => $booking->subject,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            'scheduled_label' => $booking->scheduled_at?->translatedFormat('d M Y, H:i'),
            'duration_minutes' => $booking->duration_minutes,
            'amount' => (float) $booking->amount,
            'status' => $booking->status,
            'notes' => $booking->notes,
            'payment_status' => $booking->payment?->status,
            'conversation_id' => $booking->conversation?->id,
            'student' => $booking->student ? ['id' => $booking->student->id, 'name' => $booking->student->name] : null,
            'tutor' => $booking->tutor ? [
                'id' => $booking->tutor->id,
                'name' => $booking->tutor->name,
                'domain' => $profile?->domain,
            ] : null,
            'person' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
        ];
    }

    private function conversationCard(Conversation $conversation, User $viewer): array
    {
        $other = $viewer->id === $conversation->student_id ? $conversation->tutor : $conversation->student;
        $message = $conversation->messages->first();

        return [
            'id' => $conversation->id,
            'other_user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            'last_message' => $message?->body,
            'last_message_at' => $message?->created_at?->diffForHumans(),
        ];
    }

    private function reviewCard(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'student' => $review->student?->name,
            'created_at' => $review->created_at?->diffForHumans(),
        ];
    }

    private function unreadMessagesFor(User $user): int
    {
        return Message::query()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->whereHas('conversation', function ($query) use ($user) {
                $query->where('student_id', $user->id)->orWhere('tutor_id', $user->id);
            })
            ->count();
    }
}
