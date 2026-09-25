<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GhostRaced extends Notification
{
    public function __construct(
        public readonly User $actor,
        public readonly bool $won,
        public readonly int $rematchTestId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->won ? 'ghost_won' : 'ghost_raced',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'url' => '/?ghost='.$this->rematchTestId,
        ];
    }

    /**
     * Push to the user's open tabs straight away, without waiting on a queue worker.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
