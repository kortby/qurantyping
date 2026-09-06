<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification
{
    public function __construct(public readonly User $actor) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_accepted',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'url' => '/friends',
        ];
    }
}
