<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'share_token',
        'surah_number',
        'surah_name_english',
        'surah_name_arabic',
        'ayah_count',
        'accuracy',
        'issued_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            $certificate->share_token ??= Str::lower(Str::random(10));
        });
    }

    protected function casts(): array
    {
        return [
            'accuracy' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
