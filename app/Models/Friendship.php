<?php

namespace App\Models;

use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The user who sent the friend request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who received the friend request.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}
