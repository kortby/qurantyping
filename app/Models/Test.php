<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quran_text_id',
        'mode',
        'duration',
        'wpm',
        'raw_wpm',
        'accuracy',
        'char_count',
        'correct_chars',
        'start_ayah',
        'end_ayah',
    ];

    /**
     * Get the user that owns the test.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Quranic text used for the test.
     */
    public function quranText(): BelongsTo
    {
        return $this->belongsTo(QuranText::class);
    }

    /**
     * Get the detailed result associated with the test.
     */
    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    /**
     * The tags that belong to the test.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
