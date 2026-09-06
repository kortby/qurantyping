<?php

namespace App\Events\Race;

class RaceStarted extends RaceEvent
{
    public function broadcastAs(): string
    {
        return 'started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->snapshot();
    }
}
