<?php

namespace App\Jobs;

use App\Models\Race;
use App\Services\RaceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartRaceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $raceId) {}

    public function handle(RaceService $races): void
    {
        $race = Race::find($this->raceId);

        if ($race && $race->status === 'countdown') {
            $races->beginRacing($race);
        }
    }
}
