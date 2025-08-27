<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'history',
    ];

    protected function casts(): array
    {
        return [
            'history' => 'array',
        ];
    }

    /**
     * Get the test that this result belongs to.
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }
}
