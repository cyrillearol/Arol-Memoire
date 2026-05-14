<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'message' => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'attachment_url' => $this->message->attachment_path ? Storage::url($this->message->attachment_path) : null,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender?->name,
                'created_at' => $this->message->created_at?->format('H:i'),
                'read_at' => $this->message->read_at?->toIso8601String(),
            ],
        ];
    }
}