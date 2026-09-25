<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * The user who wrote the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The user the message was sent to.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Scope to the messages exchanged between two users, in either direction.
     *
     * @param  Builder<ChatMessage>  $query
     */
    public function scopeBetween(Builder $query, int $userId, int $otherId): void
    {
        $query->where(function (Builder $query) use ($userId, $otherId): void {
            $query->where(function (Builder $query) use ($userId, $otherId): void {
                $query->where('sender_id', $userId)->where('recipient_id', $otherId);
            })->orWhere(function (Builder $query) use ($userId, $otherId): void {
                $query->where('sender_id', $otherId)->where('recipient_id', $userId);
            });
        });
    }

    /**
     * The payload shape sent to the chat dock and over the socket.
     *
     * @return array{id: int, sender_id: int, recipient_id: int, body: string, read_at: ?string, created_at: string}
     */
    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'body' => $this->body,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
