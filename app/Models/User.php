<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'role',
        'status',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'tutor_id');
    }

    public function studentBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    public function tutorBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'tutor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function studentConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'student_id');
    }

    public function tutorConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'tutor_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function givenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'student_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'tutor_id');
    }

    public function reportsSubmitted(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }
}
