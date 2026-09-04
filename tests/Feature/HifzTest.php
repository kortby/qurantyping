<?php

use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahProgress;
use App\Services\HifzService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

function seedHifzAyahs(): void
{
    $rows = [];
    $make = fn (int $s, int $a, int $juz, string $name): array => [
        'surah_number' => $s, 'ayah_number' => $a, 'juz' => $juz, 'hizb_quarter' => 1, 'page' => $s,
        'text_arabic_simple' => 'كلمة كلمة كلمة كلمة',
        'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة',
        'surah_name_arabic' => $name, 'surah_name_english' => $name, 'surah_name_translation' => $name,
        'created_at' => now(), 'updated_at' => now(),
    ];
    for ($a = 1; $a <= 7; $a++) {
        $rows[] = $make(1, $a, 1, 'Al-Fatihah');
    }
    for ($a = 1; $a <= 10; $a++) {
        $rows[] = $make(2, $a, 1, 'Al-Baqarah');
    }
    QuranText::insert($rows);
}

beforeEach(fn () => seedHifzAyahs());
afterEach(fn () => travelBack());

function progressFor(User $user, int $surah, int $ayah): UserAyahProgress
{
    return UserAyahProgress::create([
        'user_id' => $user->id,
        'quran_text_id' => QuranText::where('surah_number', $surah)->where('ayah_number', $ayah)->value('id'),
        'ease' => 2.5,
        'due_on' => now()->toDateString(),
        'status' => 'learning',
    ]);
}

/* ---- HifzService::gradeFrom ---- */

it('suggests a grade from accuracy and peeks', function (float $acc, int $peeks, int $words, int $expected) {
    expect(app(HifzService::class)->gradeFrom($acc, $peeks, $words))->toBe($expected);
})->with([
    'clean, no peeks' => [99.0, 0, 10, HifzService::GRADE_EASY],
    'a couple of peeks' => [99.0, 2, 10, HifzService::GRADE_GOOD],
    'shaky accuracy' => [90.0, 1, 10, HifzService::GRADE_HARD],
    'lots of peeks' => [99.0, 8, 10, HifzService::GRADE_AGAIN],
    'poor accuracy' => [70.0, 0, 10, HifzService::GRADE_AGAIN],
]);

/* ---- HifzService::schedule ---- */

it('grows the interval on repeated Good grades', function () {
    travelTo('2026-05-01 08:00:00');
    $p = progressFor(User::factory()->create(), 1, 1);
    $svc = app(HifzService::class);

    $svc->schedule($p, HifzService::GRADE_GOOD);
    expect($p->reps)->toBe(1)->and($p->interval_days)->toBe(2)
        ->and($p->due_on->toDateString())->toBe('2026-05-03');

    $svc->schedule($p, HifzService::GRADE_GOOD);
    expect($p->reps)->toBe(2)->and($p->interval_days)->toBe(6);

    $svc->schedule($p, HifzService::GRADE_GOOD);
    expect($p->reps)->toBe(3)->and($p->interval_days)->toBeGreaterThan(6);
});

it('drops ease, resets reps and re-queues on Again', function () {
    $p = progressFor(User::factory()->create(), 1, 1);
    $svc = app(HifzService::class);
    $svc->schedule($p, HifzService::GRADE_GOOD);
    $svc->schedule($p, HifzService::GRADE_GOOD);

    $svc->schedule($p, HifzService::GRADE_AGAIN);

    expect($p->reps)->toBe(0)
        ->and($p->lapses)->toBe(1)
        ->and($p->interval_days)->toBe(1)
        ->and($p->ease)->toBeLessThan(2.5)
        ->and($p->status)->toBe('learning');
});

it('marks an ayah mature once its interval is long enough', function () {
    $p = progressFor(User::factory()->create(), 1, 1);
    $svc = app(HifzService::class);

    for ($i = 0; $i < 6; $i++) {
        $svc->schedule($p, HifzService::GRADE_EASY);
    }

    expect($p->interval_days)->toBeGreaterThanOrEqual(21)
        ->and($p->status)->toBe('review');
});

/* ---- queues ---- */

it('returns only ayahs due on or before today, capped at 20', function () {
    travelTo('2026-05-10 08:00:00');
    $user = User::factory()->create();

    progressFor($user, 1, 1)->update(['due_on' => '2026-05-08']); // overdue
    progressFor($user, 1, 2)->update(['due_on' => '2026-05-10']); // today
    progressFor($user, 1, 3)->update(['due_on' => '2026-05-20']); // future

    $due = app(HifzService::class)->due($user);

    expect($due)->toHaveCount(2);
});

it('picks the next untracked ayah, honouring a scope', function () {
    $user = User::factory()->create();
    progressFor($user, 1, 1);
    progressFor($user, 1, 2);

    $svc = app(HifzService::class);

    expect($svc->newSession($user))->toMatchArray(['surah_number' => 1, 'start_ayah' => 3]);
    expect($svc->newSession($user, 'surah', 2))->toMatchArray(['surah_number' => 2, 'start_ayah' => 1]);
});

it('grades every ayah a test covered', function () {
    $user = User::factory()->create();
    $startId = QuranText::where('surah_number', 2)->where('ayah_number', 3)->value('id');

    $test = Test::factory()->create([
        'user_id' => $user->id,
        'quran_text_id' => $startId,
        'start_ayah' => 3,
        'end_ayah' => 5,
    ]);

    $graded = app(HifzService::class)->gradeTest($test, HifzService::GRADE_GOOD);

    expect($graded)->toBe(3);
    expect(UserAyahProgress::where('user_id', $user->id)->count())->toBe(3);
    expect(UserAyahProgress::where('user_id', $user->id)->pluck('reps')->all())->toEqual([1, 1, 1]);
});

/* ---- HTTP ---- */

it('renders the hifz dashboard', function () {
    actingAs(User::factory()->create())->get('/hifz')->assertOk();
});

it('serves a new-ayah session and 404s when nothing is due', function () {
    $user = User::factory()->create();

    actingAs($user)->getJson('/hifz/session?mode=new')
        ->assertOk()
        ->assertJsonPath('surah_number', 1)
        ->assertJsonPath('mode', 'new');

    actingAs($user)->getJson('/hifz/session?mode=due')->assertStatus(404);
});

it('grades a session through the endpoint and rejects another user\'s test', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $test = Test::factory()->create([
        'user_id' => $user->id,
        'quran_text_id' => QuranText::where('surah_number', 1)->where('ayah_number', 1)->value('id'),
        'start_ayah' => 1,
        'end_ayah' => 3,
    ]);

    actingAs($user)->postJson('/hifz/grade', ['test_id' => $test->id, 'grade' => 2])
        ->assertOk()
        ->assertJsonPath('graded', 3);

    expect(UserAyahProgress::where('user_id', $user->id)->count())->toBe(3);

    actingAs($other)->postJson('/hifz/grade', ['test_id' => $test->id, 'grade' => 2])->assertForbidden();
});

it('persists hifz_level and peeks on a completed test', function () {
    $user = User::factory()->create();
    $qt = QuranText::where('surah_number', 1)->where('ayah_number', 1)->value('id');

    actingAs($user)->postJson('/test/complete', [
        'quran_text_id' => $qt,
        'wpm' => 40, 'raw_wpm' => 40, 'accuracy' => 95, 'char_count' => 50,
        'correct_chars' => 48, 'incorrect_chars' => 2, 'mode' => 'quote', 'duration' => 30,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 2,
        'hifz_level' => 3, 'peeks' => 4,
    ])->assertCreated();

    expect(Test::latest('id')->first())->hifz_level->toBe(3)->peeks->toBe(4);
});

it('updates the daily-new setting', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/user/settings/hifz-daily-new', ['hifz_daily_new' => 12])->assertRedirect();
    expect($user->fresh()->hifz_daily_new)->toBe(12);

    actingAs($user)->post('/user/settings/hifz-daily-new', ['hifz_daily_new' => 0])->assertSessionHasErrors('hifz_daily_new');
});
