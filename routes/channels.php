<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('conversations.{conversationId}', function ($user, int $conversationId) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->where(function ($query) use ($user) {
            $query->where('student_id', $user->id)->orWhere('tutor_id', $user->id);
        })
        ->exists();
});