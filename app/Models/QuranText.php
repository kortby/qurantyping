<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranText extends Model
{
    use HasFactory;

    protected $fillable = [
        'surah_number',
        'ayah_number',
        'text_arabic_simple',
        'surah_name_arabic',
        'surah_name_english',
        'surah_name_translation',
        'surah_arabic_ponctuation',
    ];

    /**
     * Get all of the tests that used this Quranic text.
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }
}
