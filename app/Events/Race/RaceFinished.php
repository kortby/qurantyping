<?php

namespace App\Events\Race;

class RaceFinished extends RaceEvent
{
    public function broadcastAs(): string
    {
        return 'finished';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->snapshot();
    }
}
