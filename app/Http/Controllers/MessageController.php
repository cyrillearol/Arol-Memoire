<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $this->ensureParticipant($conversation, $user->id);
        $conversation->load(['booking', 'student', 'tutor']);

        abort_unless(in_array($conversation->booking?->status, ['acceptee', 'terminee'], true), 403);

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

        broadcast(new ChatMessageSent($message))->toOthers();

        $conversation->touch();

        $recipient = $user->id === $conversation->student_id ? $conversation->tutor : $conversation->student;
        PlatformNotifier::send(
            $recipient,
            'Nouveau message',
            $user->name.' vous a envoyé un message'.($conversation->booking ? ' pour la séance de '.$conversation->booking->subject : '').'.',
            route('messages.index', $conversation),
            'info'
        );

        return redirect()->route('messages.index', $conversation)->with('success', 'Message envoyé.');
    }

    private function ensureParticipant(Conversation $conversation, int $userId): void
    {
        abort_unless($conversation->student_id === $userId || $conversation->tutor_id === $userId, 403);
    }

    private function conversationResource(Conversation $conversation, int $viewerId, bool $withMessages = false): array
    {
        $other = $viewerId === $conversation->student_id ? $conversation->tutor : $conversation->student;
        $lastMessage = $withMessages ? $conversation->messages->last() : $conversation->messages()->latest()->first();
        $profile = $conversation->tutor?->tutorProfile;

        return [
            'id' => $conversation->id,
            'booking_id' => $conversation->booking_id,
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
            'messages' => $withMessages ? $conversation->messages->sortBy('created_at')->values()->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'attachment_url' => $message->attachment_path ? Storage::url($message->attachment_path) : null,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'is_mine' => $message->sender_id === $viewerId,
                'created_at' => $message->created_at?->format('H:i'),
                'read_at' => $message->read_at?->toIso8601String(),
            ]) : [],
        ];
    }
}