<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Test extends Model
{
    use HasFactory;

    /**
     * Badges awarded by the TestObserver when this test was created. Transient —
     * not persisted, read by TestController::store for the results-screen toast.
     *
     * @var Collection<int, Badge>|null
     */
    public $newBadges = null;

    protected $fillable = [
        'user_id',
        'race_id',
        'quran_text_id',
        'mode',
        'duration',
        'wpm',
        'raw_wpm',
        'accuracy',
        'char_count',
        'correct_chars',
        'incorrect_chars',
        'start_ayah',
        'end_ayah',
        'total_errors',
        'is_contest_entry',
        'hifz_level',
        'peeks',
        'tashkeel',
    ];

    protected $casts = [
        'is_contest_entry' => 'boolean',
        'tashkeel' => 'boolean',
    ];

    public function scopeContestEntries($query)
    {
        return $query->where('is_contest_entry', true);
    }

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
