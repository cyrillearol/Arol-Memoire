<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $recipientId,
        public int $conversationId,
        public int $senderId,
        public string $senderName,
        public string $type,
        public ?string $mode = null,
        public array $payload = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->recipientId);
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
            'sender_name' => $this->senderName,
            'type' => $this->type,
            'mode' => $this->mode,
            'payload' => $this->payload,
            'url' => route('messages.index', $this->conversationId),
        ];
    }
}
