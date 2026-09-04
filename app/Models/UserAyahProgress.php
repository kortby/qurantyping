<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAyahProgress extends Model
{
    protected $table = 'user_ayah_progress';

    protected $fillable = [
        'user_id',
        'quran_text_id',
        'status',
        'ease',
        'interval_days',
        'reps',
        'lapses',
        'due_on',
        'last_reviewed_at',
        'last_grade',
    ];

    protected function casts(): array
    {
        return [
            'ease' => 'float',
            'due_on' => 'date',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuranText, $this>
     */
    public function quranText(): BelongsTo
    {
        return $this->belongsTo(QuranText::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
