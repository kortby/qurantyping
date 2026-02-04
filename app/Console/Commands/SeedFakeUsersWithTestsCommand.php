<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedFakeUsersWithTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-fake-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed 10 fake users (EN, FR, AR) with 2-20 tests each';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = [
            // Arabic
            'آدم منصور',
            'Jean_B', // French nickname in second position
            'ليان الزهراني',
            'ريان الشمري',
            'سارة القحطاني',
            // French Nicknames
            'Camille_V',
            'LePetitNicolas',
            // English Nicknames
            'Sam_T',
            'Emily_W',
            'Speedy_Sam',
        ];

        if (app()->isLocal()) {
            $this->info('Resetting tests and specific fake users (Local Env Only)...');

            // Clear all tests
            \App\Models\Test::query()->delete();

            // Clear existing users with these names to prevent duplicates/bloat
            \App\Models\User::whereIn('name', $names)->delete();
        }

        $arabicNames = [
            'آدم منصور',
            'ليان الزهراني',
            'ريان الشمري',
            'سارة القحطاني',
        ];

        $this->info('Seeding 10 fake users...');

        foreach ($names as $name) {
            // Ensure unique email
            $email = fake()->unique()->safeEmail();

            $user = \App\Models\User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);

            $isArabic = in_array($name, $arabicNames);

            $testCount = rand(2, 20);

            $testData = [
                'user_id' => $user->id,
            ];

            if ($isArabic) {
                // Arabic names get higher scores
                $testData['wpm'] = rand(20, 27);
                $testData['raw_wpm'] = rand(22, 30);
                $testData['accuracy'] = rand(95, 100);
            } else {
                // Others get standard/lower scores
                $testData['wpm'] = rand(5, 15);
                $testData['raw_wpm'] = rand(7, 18);
                $testData['accuracy'] = rand(80, 95);
            }

            \App\Models\Test::factory($testCount)->create($testData);

            $this->line("Created user: {$name} ({$email}) with {$testCount} tests. (Is Arabic: " . ($isArabic ? 'Yes' : 'No') . ")");
        }

        $this->info('Seeding completed successfully!');
    }
}
