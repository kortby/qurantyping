<?php

namespace App\Events\Race;

class RaceLobbyUpdated extends RaceEvent
{
    public function broadcastAs(): string
    {
        return 'lobby.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->snapshot();
    }
}
