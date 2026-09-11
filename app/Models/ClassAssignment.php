<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAssignment extends Model
{
    protected $fillable = [
        'class_id',
        'surah_number',
        'start_ayah',
        'end_ayah',
        'due_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    /** @return BelongsTo<ClassGroup, $this> */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }
}
