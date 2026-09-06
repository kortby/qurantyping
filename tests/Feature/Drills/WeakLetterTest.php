<?php

use App\Models\QuranText;
use App\Models\User;
use App\Models\UserLetterStat;
use App\Services\WeakLetterService;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;

function seedDrillAyahs(): void
{
    $make = fn (int $s, int $a, string $text): array => [
        'surah_number' => $s, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 600,
        'text_arabic_simple' => $text,
        'surah_arabic_ponctuation' => $text,
        'surah_name_arabic' => 'سورة', 'surah_name_english' => 'Surah', 'surah_name_translation' => 'Surah',
        'created_at' => now(), 'updated_at' => now(),
    ];

    QuranText::insert([
        $make(120, 1, 'ورد ورد ورد ورد ورد'),
        $make(120, 2, 'بحب بحب بحب بحب بحب بحب'),   // dense in ب
        $make(120, 3, 'ورد ورد ورد ورد ورد'),
        $make(120, 4, 'ورد ورد ورد ورد ورد'),
    ]);
}

beforeEach(function () {
    seedDrillAyahs();
    $this->user = User::factory()->create();
});

function postTest(array $overrides = []): array
{
    $id = QuranText::where('surah_number', 120)->orderBy('ayah_number')->value('id');

    return array_merge([
        'quran_text_id' => $id,
        'wpm' => 40, 'raw_wpm' => 45, 'accuracy' => 92.0,
        'char_count' => 50, 'correct_chars' => 46, 'incorrect_chars' => 4,
        'mode' => 'quote', 'duration' => 30,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 4,
    ], $overrides);
}

it('records per-character tallies for a signed-in user', function () {
    actingAs($this->user)->postJson('/test/complete', postTest([
        'char_stats' => [
            ['c' => 'ب', 'attempts' => 20, 'misses' => 5],
            ['c' => 'ا', 'attempts' => 30, 'misses' => 0],
        ],
    ]))->assertCreated();

    expect(UserLetterStat::where('user_id', $this->user->id)->count())->toBe(2);

    $ba = UserLetterStat::where('user_id', $this->user->id)->where('character', 'ب')->first();
    expect($ba->attempts)->toBe(20)->and($ba->misses)->toBe(5);
});

it('accumulates across tests', function () {
    $payload = ['char_stats' => [['c' => 'ب', 'attempts' => 10, 'misses' => 2]]];

    actingAs($this->user)->postJson('/test/complete', postTest($payload))->assertCreated();
    actingAs($this->user)->postJson('/test/complete', postTest($payload))->assertCreated();

    $ba = UserLetterStat::where('user_id', $this->user->id)->where('character', 'ب')->first();
    expect($ba->attempts)->toBe(20)->and($ba->misses)->toBe(4);
});

it('clamps misses to attempts', function () {
    actingAs($this->user)->postJson('/test/complete', postTest([
        'char_stats' => [['c' => 'ب', 'attempts' => 8, 'misses' => 99]],
    ]))->assertCreated();

    $ba = UserLetterStat::where('user_id', $this->user->id)->where('character', 'ب')->first();
    expect($ba->misses)->toBe(8);
});

it('never lets a whitespace or blank char_stats row block the result', function () {
    actingAs($this->user)->postJson('/test/complete', postTest([
        'char_stats' => [
            ['c' => ' ', 'attempts' => 12, 'misses' => 0],
            ['c' => '', 'attempts' => 3, 'misses' => 1],
            ['c' => 'ب', 'attempts' => 10, 'misses' => 2],
        ],
    ]))->assertCreated();

    expect(UserLetterStat::where('user_id', $this->user->id)->pluck('character')->all())->toBe(['ب']);
});

it('does not record letter stats for guests', function () {
    $this->postJson('/test/complete', postTest([
        'char_stats' => [['c' => 'ب', 'attempts' => 10, 'misses' => 3]],
    ]))->assertCreated();

    expect(UserLetterStat::count())->toBe(0);
});

it('flags only characters past the confidence and severity bars, worst first, capped', function () {
    Config::set('drills.min_attempts', 25);
    Config::set('drills.weak_threshold', 0.10);
    Config::set('drills.max_chars', 2);

    UserLetterStat::factory()->for($this->user)->create(['character' => 'ب', 'attempts' => 100, 'misses' => 40]); // 40% weak
    UserLetterStat::factory()->for($this->user)->create(['character' => 'ت', 'attempts' => 100, 'misses' => 20]); // 20% weak
    UserLetterStat::factory()->for($this->user)->create(['character' => 'ث', 'attempts' => 100, 'misses' => 15]); // 15% weak but capped out
    UserLetterStat::factory()->for($this->user)->create(['character' => 'ج', 'attempts' => 10, 'misses' => 9]);   // too few attempts
    UserLetterStat::factory()->for($this->user)->create(['character' => 'ح', 'attempts' => 100, 'misses' => 2]);  // below threshold

    $weak = app(WeakLetterService::class)->weakChars($this->user->id);

    expect($weak->pluck('character')->all())->toBe(['ب', 'ت']);
});

it('builds a drill passage covering the ayah densest in the weak character', function () {
    $weak = collect([['character' => 'ب', 'miss_rate' => 0.5]]);

    $loc = app(WeakLetterService::class)->drillPassage($weak);

    expect($loc['surah_number'])->toBe(120)
        ->and($loc['start_ayah'])->toBeLessThanOrEqual(2)
        ->and($loc['end_ayah'])->toBeGreaterThanOrEqual(2);
});

it('returns null when there are no weak characters', function () {
    expect(app(WeakLetterService::class)->drillPassage(collect()))->toBeNull();
});

it('guards the drills page behind auth', function () {
    $this->get('/drills')->assertRedirect('/login');
});

it('renders the drills page for a signed-in user', function () {
    UserLetterStat::factory()->for($this->user)->create(['character' => 'ب', 'attempts' => 100, 'misses' => 40]);

    actingAs($this->user)->get('/drills')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Drills/Index')
            ->where('ready', true)
            ->has('weak', 1)
            ->has('letters', 1)
        );
});

it('serves a drill passage endpoint, falling back to a random passage without data', function () {
    actingAs($this->user)->getJson('/test/drill')
        ->assertOk()
        ->assertJsonStructure(['surah_number', 'start_ayah', 'end_ayah', 'text_simple']);
});
