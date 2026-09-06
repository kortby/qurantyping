<?php

namespace App\Events\Race;

use App\Models\Race;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RaceProgress implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public string $channelKey,
        public int $userId,
        public float $pct,
        public int $wpm,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('race.'.$this->channelKey);
    }

    public function broadcastAs(): string
    {
        return 'progress.tick';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->userId, 'pct' => $this->pct, 'wpm' => $this->wpm];
    }

    public static function fromRace(Race $race, int $userId, float $pct, int $wpm): self
    {
        return new self($race->channelKey(), $userId, $pct, $wpm);
    }
}
