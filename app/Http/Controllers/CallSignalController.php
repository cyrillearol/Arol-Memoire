<?php

namespace App\Http\Controllers;

use App\Events\ConversationCallSignal;
use App\Events\UserCallSignal;
use App\Models\Conversation;
use App\Support\RealtimeBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CallSignalController extends Controller
{
    public function store(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        abort_unless(
            $conversation->student_id === $user->id || $conversation->tutor_id === $user->id,
            403
        );

        $conversation->load(['booking', 'student', 'tutor']);
        abort_unless(
            $conversation->booking?->status === 'acceptee' || $this->isDirectAdminTutorConversation($conversation),
            403
        );

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:call-offer,call-answer,ice-candidate,call-end,call-decline'],
            'mode' => ['nullable', 'string', 'in:audio,video'],
            'payload' => ['nullable', 'array'],
        ]);

        $recipient = $user->id === $conversation->student_id
            ? $conversation->tutor
            : $conversation->student;

        RealtimeBroadcaster::send(new ConversationCallSignal(
            conversationId: $conversation->id,
            senderId: $user->id,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ), true);

        RealtimeBroadcaster::send(new UserCallSignal(
            recipientId: $recipient->id,
            conversationId: $conversation->id,
            senderId: $user->id,
            senderName: $user->name,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ), true);

        return response()->noContent();
    }

    private function isDirectAdminTutorConversation(Conversation $conversation): bool
    {
        return $conversation->booking_id === null
            && $conversation->student?->role === 'admin'
            && $conversation->tutor?->role === 'tuteur'
            && $conversation->tutor?->status === 'actif';
    }
}
