<?php

namespace App\Http\Controllers;

use App\Events\ConversationCallSignal;
use App\Models\Conversation;
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

        $conversation->load('booking');
        abort_unless($conversation->booking?->status === 'acceptee', 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:call-offer,call-answer,ice-candidate,call-end,call-decline'],
            'mode' => ['nullable', 'string', 'in:audio,video'],
            'payload' => ['nullable', 'array'],
        ]);

        broadcast(new ConversationCallSignal(
            conversationId: $conversation->id,
            senderId: $user->id,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ))->toOthers();

        return response()->noContent();
    }
}
