<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_profile_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
