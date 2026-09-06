<?php

namespace App\Events\Race;

class RaceStarting extends RaceEvent
{
    public function broadcastAs(): string
    {
        return 'starting';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge($this->snapshot(), [
            'text' => $this->race->text,
            'char_target' => $this->race->char_target,
        ]);
    }
}
