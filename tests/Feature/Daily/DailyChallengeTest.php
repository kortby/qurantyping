<?php

use App\Models\DailyChallengeRun;
use App\Models\Friendship;
use App\Models\QuranText;
use App\Models\User;
use App\Services\DailyChallengeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

function seedDailySurah(int $surahNumber): void
{
    $rows = [];
    for ($a = 1; $a <= 12; $a++) {
        $rows[] = [
            'surah_number' => $surahNumber, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 600,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'سورة '.$surahNumber, 'surah_name_english' => 'Surah '.$surahNumber, 'surah_name_translation' => 'Surah '.$surahNumber,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

function completeDaily(User $user, array $challenge, int $wpm): TestResponse
{
    $firstAyah = QuranText::where('surah_number', $challenge['surah_number'])
        ->where('ayah_number', $challenge['start_ayah'])
        ->first();

    return actingAs($user)->postJson('/test/complete', [
        'quran_text_id' => $firstAyah->id,
        'wpm' => $wpm, 'raw_wpm' => $wpm, 'accuracy' => 95,
        'char_count' => 40, 'correct_chars' => 38, 'incorrect_chars' => 2,
        'mode' => 'quote', 'duration' => 20,
        'start_ayah' => $challenge['start_ayah'], 'end_ayah' => $challenge['end_ayah'],
        'total_errors' => 2, 'daily' => true,
    ]);
}

beforeEach(function () {
    Cache::flush();
    seedDailySurah(100);
    seedDailySurah(101);
    seedDailySurah(102);
});

it('derives the same passage for a date and stays within bounds', function () {
    $service = app(DailyChallengeService::class);

    $a = $service->forDate(Carbon::parse('2026-06-01'));
    $b = $service->forDate(Carbon::parse('2026-06-01'));

    expect($a)->toBe($b)
        ->and($a['start_ayah'])->toBeGreaterThanOrEqual(1)
        ->and($a['end_ayah'])->toBeLessThanOrEqual(12)
        ->and($a['end_ayah'] - $a['start_ayah'])->toBe(3)
        ->and([100, 101, 102])->toContain($a['surah_number']);
});

it('varies the passage across dates', function () {
    $service = app(DailyChallengeService::class);

    $keys = collect(range(1, 20))
        ->map(fn (int $d): string => (function ($c) {
            return "{$c['surah_number']}:{$c['start_ayah']}";
        })($service->forDate(Carbon::parse('2026-06-'.sprintf('%02d', $d)))))
        ->unique();

    expect($keys->count())->toBeGreaterThan(1);
});

it('serves today\'s passage as JSON to anyone', function () {
    $this->getJson('/api/daily')
        ->assertOk()
        ->assertJsonStructure(['date', 'surah_number', 'surah_name_arabic', 'start_ayah', 'end_ayah']);
});

it('renders the /today page for a signed-in user', function () {
    actingAs(User::factory()->create())->get('/today')->assertOk();
});

it('credits a matching run as the daily and keeps the best score', function () {
    $me = User::factory()->create();
    $challenge = app(DailyChallengeService::class)->today();

    completeDaily($me, $challenge, 40)->assertCreated();
    completeDaily($me, $challenge, 30)->assertCreated();
    completeDaily($me, $challenge, 55)->assertCreated();

    $run = DailyChallengeRun::where('user_id', $me->id)->get();

    expect($run)->toHaveCount(1)
        ->and($run->first()->wpm)->toBe(55);
});

it('does not credit a run on a different passage', function () {
    $me = User::factory()->create();
    $challenge = app(DailyChallengeService::class)->today();
    $otherSurah = collect([100, 101, 102])->first(fn (int $s): bool => $s !== $challenge['surah_number']);
    $firstAyah = QuranText::where('surah_number', $otherSurah)->where('ayah_number', 1)->first();

    actingAs($me)->postJson('/test/complete', [
        'quran_text_id' => $firstAyah->id,
        'wpm' => 50, 'raw_wpm' => 50, 'accuracy' => 95,
        'char_count' => 40, 'correct_chars' => 38, 'incorrect_chars' => 2,
        'mode' => 'quote', 'duration' => 20,
        'start_ayah' => 1, 'end_ayah' => 4, 'total_errors' => 2, 'daily' => true,
    ])->assertCreated();

    expect(DailyChallengeRun::count())->toBe(0);
});

it('flips the daily_done shared prop once completed', function () {
    $me = User::factory()->create();
    $challenge = app(DailyChallengeService::class)->today();

    actingAs($me)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.daily_done', false));

    completeDaily($me, $challenge, 42)->assertCreated();

    actingAs($me)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.daily_done', true));
});

it('shows friends plus self on the /today board, ranked by wpm', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $me->id, 'friend_id' => $friend->id]);
    $challenge = app(DailyChallengeService::class)->today();

    completeDaily($me, $challenge, 40);
    completeDaily($friend, $challenge, 70);

    actingAs($me)->get('/today')->assertInertia(fn ($page) => $page
        ->where('board.0.name', $friend->name)
        ->where('board.1.name', $me->name)
        ->where('board.1.is_me', true)
    );
});
