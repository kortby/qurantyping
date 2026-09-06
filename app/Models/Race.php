<?php

namespace App\Models;

use Database\Factories\RaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Race extends Model
{
    /** @use HasFactory<RaceFactory> */
    use HasFactory;

    public const CAPACITY = 5;

    public const MIN_TO_START = 2;

    public const COUNTDOWN_SECONDS = 8;

    protected $fillable = [
        'code',
        'visibility',
        'status',
        'host_user_id',
        'quran_text_id',
        'surah_number',
        'start_ayah',
        'end_ayah',
        'text',
        'char_target',
        'tashkeel',
        'capacity',
        'scope_surah',
        'starts_at',
        'finished_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'finished_at' => 'datetime',
            'tashkeel' => 'boolean',
        ];
    }

    public function seatLimit(): int
    {
        return (int) ($this->capacity ?: self::CAPACITY);
    }

    /** @return HasMany<RaceParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(RaceParticipant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /** @return BelongsTo<QuranText, $this> */
    public function quranText(): BelongsTo
    {
        return $this->belongsTo(QuranText::class);
    }

    /**
     * The presence-channel key: the share code for private rooms, "q<id>" otherwise.
     */
    public function channelKey(): string
    {
        return $this->code ?? 'q'.$this->id;
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, ['lobby', 'countdown'], true)
            && $this->participants()->count() < $this->seatLimit();
    }
}
