<?php

namespace App\Events\Race;

use App\Models\Race;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class RaceEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Race $race) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('race.'.$this->race->channelKey());
    }

    /**
     * The current lobby / standings snapshot, included on every race event so a
     * client that missed an earlier message can still reconcile.
     *
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        return [
            'status' => $this->race->status,
            'starts_at' => $this->race->starts_at?->toIso8601String(),
            'participants' => $this->race->participants()
                ->with('user:id,name')
                ->orderBy('position')
                ->orderBy('joined_at')
                ->get()
                ->map(fn ($p): array => [
                    'id' => $p->user_id,
                    'name' => $p->user?->name,
                    'finished' => (bool) $p->finished_at,
                    'position' => $p->position,
                    'wpm' => $p->wpm,
                    'accuracy' => $p->accuracy !== null ? (float) $p->accuracy : null,
                ])
                ->all(),
        ];
    }
}
