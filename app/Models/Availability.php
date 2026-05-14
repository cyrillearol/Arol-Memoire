<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_id',
        'weekday',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }
}
