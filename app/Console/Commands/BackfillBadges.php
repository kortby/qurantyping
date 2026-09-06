<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Console\Command;

class BackfillBadges extends Command
{
    protected $signature = 'badges:backfill';

    protected $description = 'Award every badge existing users already qualify for (idempotent)';

    public function handle(BadgeService $badges): int
    {
        $users = 0;
        $awarded = 0;

        User::query()->chunkById(200, function ($chunk) use ($badges, &$users, &$awarded): void {
            foreach ($chunk as $user) {
                $awarded += $badges->evaluate($user)->count();
                $users++;
            }
        });

        $this->info("Checked {$users} users, awarded {$awarded} badge(s).");

        return self::SUCCESS;
    }
}
