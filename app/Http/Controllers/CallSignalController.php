<?php

namespace App\Http\Controllers;

use App\Events\ConversationCallSignal;
use App\Events\UserCallSignal;
use App\Models\CallSignal;
use App\Models\Conversation;
use App\Support\RealtimeBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

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

        $signalId = 0;

        if (Schema::hasTable('call_signals')) {
            $signal = CallSignal::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'type' => $validated['type'],
                'mode' => $validated['mode'] ?? null,
                'payload' => $validated['payload'] ?? [],
            ]);

            $signalId = $signal->id;

            CallSignal::query()
                ->where('created_at', '<', now()->subMinutes(10))
                ->delete();
        }

        RealtimeBroadcaster::send(new ConversationCallSignal(
            signalId: $signalId,
            conversationId: $conversation->id,
            senderId: $user->id,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ), true);

        RealtimeBroadcaster::send(new UserCallSignal(
            recipientId: $recipient->id,
            signalId: $signalId,
            conversationId: $conversation->id,
            senderId: $user->id,
            senderName: $user->name,
            type: $validated['type'],
            mode: $validated['mode'] ?? null,
            payload: $validated['payload'] ?? [],
        ), true);

        return response()->noContent();
    }

    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $after = (int) ($validated['after'] ?? 0);

        if (! Schema::hasTable('call_signals')) {
            return response()->json([
                'signals' => [],
                'latest_id' => $after,
            ]);
        }

        $baseQuery = CallSignal::query()
            ->where('recipient_id', $request->user()->id);

        if ($request->boolean('initial')) {
            return response()->json([
                'signals' => [],
                'latest_id' => max($after, (int) $baseQuery->max('id')),
            ]);
        }

        $signals = (clone $baseQuery)
            ->with('sender:id,name')
            ->where('id', '>', $after)
            ->where('created_at', '>=', now()->subMinute())
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (CallSignal $signal) => [
                'signal_id' => $signal->id,
                'conversation_id' => $signal->conversation_id,
                'sender_id' => $signal->sender_id,
                'sender_name' => $signal->sender?->name ?? 'Un utilisateur',
                'type' => $signal->type,
                'mode' => $signal->mode,
                'payload' => $signal->payload ?? [],
                'url' => route('messages.index', $signal->conversation_id),
            ])
            ->values();

        return response()->json([
            'signals' => $signals,
            'latest_id' => max($after, (int) ($signals->max('signal_id') ?? 0)),
        ]);
    }

    private function isDirectAdminTutorConversation(Conversation $conversation): bool
    {
        return $conversation->booking_id === null
            && $conversation->student?->role === 'admin'
            && $conversation->tutor?->role === 'tuteur'
            && $conversation->tutor?->status === 'actif';
    }
}
