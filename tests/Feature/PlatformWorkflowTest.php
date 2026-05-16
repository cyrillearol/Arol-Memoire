<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_validate_reject_and_suspend_tutors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pendingTutor = $this->createTutor(['status' => 'en_attente']);
        $rejectedTutor = $this->createTutor(['status' => 'en_attente']);

        $this->actingAs($admin)
            ->patch(route('admin.tutors.accept', $pendingTutor))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $pendingTutor->id,
            'status' => 'actif',
        ]);

        $conversation = Conversation::query()->whereNull('booking_id')->where([
            'student_id' => $admin->id,
            'tutor_id' => $pendingTutor->id,
        ])->first();

        $this->assertNotNull($conversation);

        $this->actingAs($admin)
            ->post(route('messages.store', $conversation), [
                'body' => 'Bienvenue dans l espace tuteur.',
            ])
            ->assertRedirect(route('messages.index', $conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => 'Bienvenue dans l espace tuteur.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tutors.message', $pendingTutor))
            ->assertRedirect(route('messages.index', $conversation));

        $this->actingAs($admin)
            ->patch(route('admin.tutors.suspend', $pendingTutor))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $pendingTutor->id,
            'status' => 'suspendu',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tutors.reject', $rejectedTutor))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $rejectedTutor->id,
            'status' => 'rejete',
        ]);
    }

    public function test_public_pages_only_expose_validated_tutors(): void
    {
        $activeTutor = $this->createTutor(['name' => 'Tuteur Visible', 'status' => 'actif']);
        $this->createTutor(['name' => 'Tuteur En Attente', 'status' => 'en_attente']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('publicTutors', 1)
                ->where('publicTutors.0.id', $activeTutor->id)
            );

        $this->get(route('tutors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tutors/Index')
                ->has('tutors.data', 1)
                ->where('tutors.data.0.id', $activeTutor->id)
            );
    }

    public function test_dashboard_renders_the_page_matching_the_user_role(): void
    {
        $student = User::factory()->create(['role' => 'etudiant']);
        $tutor = $this->createTutor(['status' => 'actif']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Student'));

        $this->actingAs($tutor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Tutor'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Admin'));
    }

    public function test_student_booking_creates_payment_and_acceptance_opens_conversation(): void
    {
        $student = User::factory()->create(['role' => 'etudiant']);
        $tutor = $this->createTutor(['status' => 'actif']);
        $scheduledAt = Carbon::now()->addDays(2)->setTime(10, 0);

        $tutor->availabilities()->create([
            'weekday' => $scheduledAt->isoWeekday(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_available' => true,
        ]);

        $this->actingAs($student)
            ->post(route('bookings.store', $tutor), [
                'subject' => 'Algèbre',
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
                'payment_method' => 'kkiapay',
                'kkiapay_transaction_id' => 'test_kkiapay_success_001',
                'notes' => 'Préparer les exercices du chapitre 3.',
            ])
            ->assertRedirect(route('dashboard'));

        $booking = Booking::firstOrFail();

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'user_id' => $student->id,
            'status' => 'valide',
            'method' => 'kkiapay',
            'transaction_reference' => 'test_kkiapay_success_001',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $tutor->id,
            'read_at' => null,
        ]);

        $notificationId = DB::table('notifications')
            ->where('notifiable_id', $tutor->id)
            ->value('id');

        $this->actingAs($tutor)
            ->patch(route('notifications.read', $notificationId))
            ->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $notificationId)->value('read_at'));

        $this->actingAs($tutor)
            ->patch(route('bookings.accept', $booking))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'acceptee',
        ]);

        $this->assertDatabaseHas('conversations', [
            'booking_id' => $booking->id,
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $student->id,
            'read_at' => null,
        ]);
    }

    public function test_student_cannot_book_outside_tutor_availability(): void
    {
        $student = User::factory()->create(['role' => 'etudiant']);
        $tutor = $this->createTutor(['status' => 'actif']);
        $scheduledAt = Carbon::now()->addDays(2)->setTime(10, 0);

        $this->actingAs($student)
            ->from(route('bookings.create', $tutor))
            ->post(route('bookings.store', $tutor), [
                'subject' => 'Algèbre',
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
                'payment_method' => 'kkiapay',
                'kkiapay_transaction_id' => 'test_kkiapay_success_002',
            ])
            ->assertRedirect(route('bookings.create', $tutor))
            ->assertSessionHasErrors('scheduled_at');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_conversation_participants_can_send_call_signals(): void
    {
        $student = User::factory()->create(['role' => 'etudiant']);
        $tutor = $this->createTutor(['status' => 'actif']);
        $booking = Booking::query()->create([
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
            'subject' => 'Algèbre',
            'scheduled_at' => Carbon::now()->addDay()->setTime(10, 0),
            'duration_minutes' => 60,
            'amount' => 5000,
            'status' => 'acceptee',
        ]);
        $conversation = Conversation::query()->create([
            'booking_id' => $booking->id,
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('calls.signal', $conversation), [
                'type' => 'call-offer',
                'mode' => 'video',
                'payload' => [
                    'description' => [
                        'type' => 'offer',
                        'sdp' => 'fake-sdp',
                    ],
                ],
            ])
            ->assertNoContent();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('call_signals', [
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'recipient_id' => $tutor->id,
            'type' => 'call-offer',
            'mode' => 'video',
        ]);

        $this->actingAs($tutor)
            ->getJson(route('calls.pending'))
            ->assertOk()
            ->assertJsonPath('signals.0.conversation_id', $conversation->id)
            ->assertJsonPath('signals.0.sender_id', $student->id)
            ->assertJsonPath('signals.0.sender_name', $student->name)
            ->assertJsonPath('signals.0.type', 'call-offer')
            ->assertJsonPath('signals.0.mode', 'video')
            ->assertJsonPath('signals.0.payload.description.type', 'offer');

        $this->actingAs($student)
            ->getJson(route('calls.pending'))
            ->assertOk()
            ->assertJsonCount(0, 'signals');
    }

    public function test_call_signals_require_a_participant_and_an_accepted_booking(): void
    {
        $student = User::factory()->create(['role' => 'etudiant']);
        $outsider = User::factory()->create(['role' => 'etudiant']);
        $tutor = $this->createTutor(['status' => 'actif']);
        $acceptedBooking = Booking::query()->create([
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
            'subject' => 'Algèbre',
            'scheduled_at' => Carbon::now()->addDay()->setTime(10, 0),
            'duration_minutes' => 60,
            'amount' => 5000,
            'status' => 'acceptee',
        ]);
        $acceptedConversation = Conversation::query()->create([
            'booking_id' => $acceptedBooking->id,
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $this->actingAs($outsider)
            ->postJson(route('calls.signal', $acceptedConversation), [
                'type' => 'call-offer',
                'mode' => 'audio',
                'payload' => [],
            ])
            ->assertForbidden();

        $pendingBooking = Booking::query()->create([
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
            'subject' => 'Statistiques',
            'scheduled_at' => Carbon::now()->addDays(2)->setTime(14, 0),
            'duration_minutes' => 60,
            'amount' => 5000,
            'status' => 'en_attente',
        ]);
        $pendingConversation = Conversation::query()->create([
            'booking_id' => $pendingBooking->id,
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('calls.signal', $pendingConversation), [
                'type' => 'call-offer',
                'mode' => 'audio',
                'payload' => [],
            ])
            ->assertForbidden();
    }

    public function test_authenticated_user_can_authorize_personal_broadcast_channel(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);

        app(\Illuminate\Broadcasting\BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');

        $user = User::factory()->create(['role' => 'etudiant']);

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-users.'.$user->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    private function createTutor(array $attributes = []): User
    {
        $tutor = User::factory()->create([
            'role' => 'tuteur',
            'status' => $attributes['status'] ?? 'actif',
            'name' => $attributes['name'] ?? 'Tuteur Test',
        ]);

        $tutor->tutorProfile()->create([
            'domain' => 'Mathématiques',
            'subjects' => ['Algèbre', 'Statistiques'],
            'hourly_rate' => 5000,
            'bio' => 'Accompagnement structuré pour aider les étudiants à progresser durablement.',
        ]);

        return $tutor;
    }
}
