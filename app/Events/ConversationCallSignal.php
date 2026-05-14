<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $senderId,
        public string $type,
        public ?string $mode = null,
        public array $payload = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->senderId,
            'type' => $this->type,
            'mode' => $this->mode,
            'payload' => $this->payload,
        ];
    }
}
