<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PlatformNotifier;
use App\Support\RealtimeBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MessageController extends Controller
{
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        if ($conversation) {
            $this->ensureParticipant($conversation, $user->id);
        }

        $conversations = Conversation::query()
            ->with(['student', 'tutor.tutorProfile', 'booking'])
            ->where(function ($query) use ($user) {
                $query->where('student_id', $user->id)->orWhere('tutor_id', $user->id);
            })
            ->latest('updated_at')
            ->get();

        $selected = $conversation ?: $conversations->first();

        if ($selected) {
            $this->ensureParticipant($selected, $user->id);
            $selected->load(['student', 'tutor.tutorProfile', 'booking', 'messages.sender']);

            Message::query()
                ->where('conversation_id', $selected->id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations->map(fn (Conversation $item) => $this->conversationResource($item, $user->id)),
            'selectedConversation' => $selected ? $this->conversationResource($selected, $user->id, true) : null,
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureParticipant($conversation, $user->id);
        $conversation->load(['booking', 'student', 'tutor']);

        abort_unless($this->conversationAllowsMessages($conversation), 403);

        $validated = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('message-documents', 'public');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $path,
        ]);

        RealtimeBroadcaster::send(new ChatMessageSent($message), true);

        $conversation->touch();

        $recipient = $user->id === $conversation->student_id ? $conversation->tutor : $conversation->student;
        PlatformNotifier::send(
            $recipient,
            'Nouveau message',
            $user->name.' vous a envoyé un message'.($conversation->booking ? ' pour la séance de '.$conversation->booking->subject : '').'.',
            route('messages.index', $conversation),
            'info'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->messageResource($message->loadMissing('sender'), $user->id),
            ]);
        }

        return redirect()->route('messages.index', $conversation)->with('success', 'Message envoyé.');
    }

    public function downloadAttachment(Request $request, Message $message): BinaryFileResponse
    {
        $message->loadMissing('conversation');
        $this->ensureParticipant($message->conversation, $request->user()->id);

        abort_if(blank($message->attachment_path), 404);
        abort_unless(Storage::disk('public')->exists($message->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($message->attachment_path));
    }

    private function ensureParticipant(Conversation $conversation, int $userId): void
    {
        abort_unless($conversation->student_id === $userId || $conversation->tutor_id === $userId, 403);
    }

    private function conversationAllowsMessages(Conversation $conversation): bool
    {
        if (in_array($conversation->booking?->status, ['acceptee', 'terminee'], true)) {
            return true;
        }

        return $this->isDirectAdminTutorConversation($conversation);
    }

    private function conversationAllowsCalls(Conversation $conversation): bool
    {
        if ($conversation->booking?->status === 'acceptee') {
            return true;
        }

        return $this->isDirectAdminTutorConversation($conversation);
    }

    private function isDirectAdminTutorConversation(Conversation $conversation): bool
    {
        $conversation->loadMissing(['student', 'tutor']);

        return $conversation->booking_id === null
            && $conversation->student?->role === 'admin'
            && $conversation->tutor?->role === 'tuteur'
            && $conversation->tutor?->status === 'actif';
    }

    private function conversationResource(Conversation $conversation, int $viewerId, bool $withMessages = false): array
    {
        $other = $viewerId === $conversation->student_id ? $conversation->tutor : $conversation->student;
        $lastMessage = $withMessages ? $conversation->messages->last() : $conversation->messages()->latest()->first();
        $profile = $conversation->tutor?->tutorProfile;

        return [
            'id' => $conversation->id,
            'booking_id' => $conversation->booking_id,
            'type' => $conversation->booking_id ? 'reservation' : 'admin_tuteur',
            'can_call' => $this->conversationAllowsCalls($conversation),
            'booking' => $conversation->booking ? [
                'id' => $conversation->booking->id,
                'subject' => $conversation->booking->subject,
                'scheduled_label' => $conversation->booking->scheduled_at?->translatedFormat('d M Y, H:i'),
                'status' => $conversation->booking->status,
            ] : null,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'role' => $other->role,
                'domain' => $other->id === $conversation->tutor_id ? $profile?->domain : null,
            ] : null,
            'last_message' => $lastMessage?->body,
            'last_message_at' => $lastMessage?->created_at?->diffForHumans(),
            'messages' => $withMessages ? $conversation->messages
                ->sortBy('created_at')
                ->values()
                ->map(fn (Message $message) => $this->messageResource($message, $viewerId)) : [],
        ];
    }

    private function messageResource(Message $message, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'attachment_url' => $message->attachment_path ? route('messages.attachments.show', $message, false) : null,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'is_mine' => $message->sender_id === $viewerId,
            'created_at' => $message->created_at?->format('H:i'),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }
}