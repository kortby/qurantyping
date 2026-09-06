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

    /**
     * Send a friend request from one user to another, accepting instantly if the
     * target already has a pending request out to the sender.
     *
     * @return 'self'|'friends'|'exists'|'accepted'|'sent'
     */
    public static function request(User $from, int $toId): string
    {
        if ($toId === $from->id) {
            return 'self';
        }

        $existing = static::query()
            ->where(function ($query) use ($from, $toId): void {
                $query->where('user_id', $from->id)->where('friend_id', $toId);
            })
            ->orWhere(function ($query) use ($from, $toId): void {
                $query->where('user_id', $toId)->where('friend_id', $from->id);
            })
            ->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                return 'friends';
            }

            if ($existing->friend_id === $from->id) {
                $existing->update(['status' => 'accepted', 'accepted_at' => now()]);

                return 'accepted';
            }

            return 'exists';
        }

        static::create(['user_id' => $from->id, 'friend_id' => $toId]);

        return 'sent';
    }
}
