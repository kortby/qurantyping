<?php

namespace App\Models;

use Database\Factories\UserLetterStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLetterStat extends Model
{
    /** @use HasFactory<UserLetterStatFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'character',
        'attempts',
        'misses',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
