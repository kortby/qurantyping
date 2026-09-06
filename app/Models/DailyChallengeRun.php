<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyChallengeRun extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_date',
        'test_id',
        'wpm',
        'accuracy',
    ];

    protected function casts(): array
    {
        return [
            'challenge_date' => 'date',
            'wpm' => 'integer',
            'accuracy' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }
}
