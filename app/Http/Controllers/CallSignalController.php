<?php

namespace App\Http\Controllers;

use App\Events\ConversationCallSignal;
use App\Models\Conversation;
use App\Support\PlatformNotifier;
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
        abort_unless($conversation->booking?->status === 'acceptee', 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:call-offer,call-answer,ice-candidate,call-end,call-decline'],
            'mode' => ['nullable', 'string', 'in:audio,video'],
            'payload' => ['nullable', 'array'],
        ]);

        if ($validated['type'] === 'call-offer') {
            $recipient = $user->id === $conversation->student_id
                ? $conversation->tutor
                : $conversation->student;
            $modeLabel = ($validated['mode'] ?? 'audio') === 'video' ? 'vidéo' : 'audio';
            $subject = $conversation->booking?->subject ? ' pour la séance de '.$conversation->booking->subject : '';

            PlatformNotifier::send(
                $recipient,
                'Appel '.$modeLabel.' entrant',
                $user->name.' vous a appelé'.$subject.'.',
                route('messages.index', $conversation),
                'warning'
            );
        }

        RealtimeBroadcaster::send(new ConversationCallSignal(
            conversationId: $conversation->id,
            senderId: $user->id,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ), true);

        return response()->noContent();
    }
}
