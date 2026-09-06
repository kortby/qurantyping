<?php

namespace App\Console\Commands;

use App\Services\RaceService;
use Illuminate\Console\Command;

class ReapRaces extends Command
{
    protected $signature = 'races:reap';

    protected $description = 'Abandon stalled races and close out finished ones';

    public function handle(RaceService $races): int
    {
        $touched = $races->reapStale();
        $this->info("Reaped {$touched} race(s).");

        return self::SUCCESS;
    }
}
