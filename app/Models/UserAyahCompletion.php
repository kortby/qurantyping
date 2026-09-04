<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAyahCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'quran_text_id',
        'best_accuracy',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'best_accuracy' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<QuranText, $this>
     */
    public function quranText(): BelongsTo
    {
        return $this->belongsTo(QuranText::class);
    }
}
