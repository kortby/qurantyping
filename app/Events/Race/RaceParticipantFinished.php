<?php

namespace App\Events\Race;

use App\Models\Race;
use App\Models\RaceParticipant;

class RaceParticipantFinished extends RaceEvent
{
    public function __construct(Race $race, public RaceParticipant $participant)
    {
        parent::__construct($race);
    }

    public function broadcastAs(): string
    {
        return 'participant.finished';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge($this->snapshot(), [
            'finished' => [
                'id' => $this->participant->user_id,
                'position' => $this->participant->position,
                'wpm' => $this->participant->wpm,
                'accuracy' => (float) $this->participant->accuracy,
            ],
        ]);
    }
}
