<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\Test;
use Illuminate\Console\Command;

class AwardContestWinner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contest:award-winner {--year= : The year of the contest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Awards the winning badge to the user with the highest score during the contest.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year') ?? date('Y');

        $this->info("Calculating winner for the {$year} Ramadan Contest...");

        $winningTest = Test::with('user')
            ->where('is_contest_entry', true)
            ->whereYear('created_at', $year)
            ->orderByDesc('wpm')
            ->orderByDesc('accuracy')
            ->orderBy('created_at')
            ->first();

        if (!$winningTest) {
            $this->error("No valid contest entries found for {$year}.");
            return;
        }

        $user = $winningTest->user;

        $this->info("Winner found: {$user->name} with {$winningTest->wpm} WPM & {$winningTest->accuracy}% accuracy.");

        $shortYear = substr($year, -2);
        $badge = Badge::firstOrCreate(
            ['name' => "Ramadan '{$shortYear} Champion"],
            [
                'icon' => '🏆',
                'description' => "Awarded for achieving 1st place in the Global Ramadan Typing Contest {$year} with a stellar score of {$winningTest->wpm} WPM and {$winningTest->accuracy}% accuracy.",
            ]
        );

        if (!$user->badges()->where('badge_id', $badge->id)->exists()) {
            $user->badges()->attach($badge->id);
            $this->info("Successfully awarded '{$badge->name}' badge to {$user->name}.");
        } else {
            $this->warn("User {$user->name} already has the '{$badge->name}' badge.");
        }
    }
}
