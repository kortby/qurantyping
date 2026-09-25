<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  int  $readerId  The user who opened the conversation.
     * @param  int  $senderId  The user whose messages were marked read.
     */
    public function __construct(
        public int $readerId,
        public int $senderId,
        public int $upToId,
        public string $readAt,
    ) {}

    /**
     * The sender learns their messages were seen; the reader's other tabs clear their badge.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->senderId),
            new PrivateChannel('App.Models.User.'.$this->readerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->readerId,
            'sender_id' => $this->senderId,
            'up_to_id' => $this->upToId,
            'read_at' => $this->readAt,
        ];
    }
}
