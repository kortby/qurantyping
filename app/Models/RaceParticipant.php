<?php

namespace App\Models;

use Database\Factories\RaceParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceParticipant extends Model
{
    /** @use HasFactory<RaceParticipantFactory> */
    use HasFactory;

    protected $fillable = [
        'race_id',
        'user_id',
        'joined_at',
        'finished_at',
        'wpm',
        'accuracy',
        'chars',
        'position',
        'test_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'finished_at' => 'datetime',
            'accuracy' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Race, $this> */
    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
