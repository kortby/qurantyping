<?php

use App\Models\Badge;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a dummy Quran text to satisfy foreign key constraints without needing a factory
    DB::table('quran_texts')->insert([
        'id' => 1,
        'surah_number' => 1,
        'ayah_number' => 1,
        'text_arabic_simple' => 'Dummy Arabic text',
        'surah_name_arabic' => 'الفاتحة',
        'surah_name_english' => 'Al-Fatihah',
        'surah_name_translation' => 'The Opener',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('awards the contest champion badge to the highest scorer using is_contest_entry', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Test::unsetEventDispatcher();

    // High score but NOT a contest entry
    Test::factory()->create([
        'user_id' => $user1->id,
        'quran_text_id' => 1,
        'wpm' => 200,
        'accuracy' => 100,
        'is_contest_entry' => false,
        'created_at' => Carbon::now()->setYear(2026)
    ]);

    // Valid contest entry, but lower score
    Test::factory()->create([
        'user_id' => $user1->id,
        'quran_text_id' => 1,
        'wpm' => 150,
        'accuracy' => 99,
        'is_contest_entry' => true,
        'created_at' => Carbon::parse('2026-03-17 12:00:00')
    ]);

    // Valid contest entry, WINNER
    Test::factory()->create([
        'user_id' => $user2->id,
        'quran_text_id' => 1,
        'wpm' => 160,
        'accuracy' => 99,
        'is_contest_entry' => true,
        'created_at' => Carbon::parse('2026-03-17 12:00:00')
    ]);

    artisan('contest:award-winner', ['--year' => 2026])
        ->assertExitCode(0);

    // Verify user 2 got the badge
    expect($user2->badges()->where('name', "Ramadan '26 Champion")->exists())->toBeTrue();
    // Verify user 1 did NOT get the badge despite having a 200 WPM test
    expect($user1->badges()->where('name', "Ramadan '26 Champion")->exists())->toBeFalse();
});

it('gracefully handles missing contest entries', function () {
    artisan('contest:award-winner', ['--year' => 2025])
        ->expectsOutputToContain('No valid contest entries found')
        ->assertExitCode(0);
});
