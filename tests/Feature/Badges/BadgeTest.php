<?php

use App\Models\Badge;
use App\Models\Certificate;
use App\Models\QuranText;
use App\Models\Race;
use App\Models\RaceParticipant;
use App\Models\Test;
use App\Models\User;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BadgeSeeder::class);

    // Test::factory() needs a quran_texts row (there is no QuranTextFactory).
    QuranText::insert(collect(range(1, 4))->map(fn (int $a): array => [
        'surah_number' => 108, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 602,
        'text_arabic_simple' => 'كلمة كلمة كلمة', 'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
        'surah_name_arabic' => 'الكوثر', 'surah_name_english' => 'Al-Kawthar', 'surah_name_translation' => 'Al-Kawthar',
        'created_at' => now(), 'updated_at' => now(),
    ])->all());

    $this->user = User::factory()->create();
});

function hasBadge(User $u, string $slug): bool
{
    return $u->badges()->where('slug', $slug)->exists();
}

it('seeds the full catalogue idempotently', function () {
    $count = Badge::count();
    seed(BadgeSeeder::class);

    expect(Badge::count())->toBe($count)
        ->and($count)->toBe(count(config('badges.list')));
});

it('awards first-test after one test and tests-10 only at ten', function () {
    // The TestObserver runs evaluate() on every Test::create.
    Test::factory()->for($this->user)->create(['wpm' => 20]);

    expect(hasBadge($this->user->fresh(), 'first-test'))->toBeTrue()
        ->and(hasBadge($this->user->fresh(), 'tests-10'))->toBeFalse();

    Test::factory()->count(9)->for($this->user)->create(['wpm' => 20]);

    expect(hasBadge($this->user->fresh(), 'tests-10'))->toBeTrue();
});

it('is idempotent — no duplicate pivot rows', function () {
    Test::factory()->for($this->user)->create();

    app(BadgeService::class)->evaluate($this->user->fresh());
    app(BadgeService::class)->evaluate($this->user->fresh());

    expect($this->user->badges()->where('slug', 'first-test')->count())->toBe(1);
});

it('awards wpm, streak, race and certificate badges from their sources', function () {
    Test::factory()->for($this->user)->create(['wpm' => 65]);
    $this->user->forceFill(['longest_streak' => 8])->save();   // not mass-assignable

    $race = Race::factory()->create(['status' => 'finished']);
    RaceParticipant::create([
        'race_id' => $race->id, 'user_id' => $this->user->id, 'joined_at' => now(),
        'finished_at' => now(), 'wpm' => 50, 'accuracy' => 90, 'chars' => 100, 'position' => 1,
    ]);

    Certificate::create([
        'user_id' => $this->user->id, 'surah_number' => 112, 'surah_name_english' => 'Al-Ikhlas',
        'surah_name_arabic' => 'الإخلاص', 'ayah_count' => 4, 'accuracy' => 99, 'issued_at' => now(),
    ]);

    app(BadgeService::class)->evaluate($this->user->fresh());
    $u = $this->user->fresh();

    expect(hasBadge($u, 'wpm-40'))->toBeTrue()
        ->and(hasBadge($u, 'wpm-60'))->toBeTrue()
        ->and(hasBadge($u, 'streak-7'))->toBeTrue()
        ->and(hasBadge($u, 'race-win'))->toBeTrue()
        ->and(hasBadge($u, 'first-surah'))->toBeTrue()
        ->and(hasBadge($u, 'wpm-80'))->toBeFalse()
        ->and(hasBadge($u, 'streak-30'))->toBeFalse();
});

it('returns newly earned badges in the /test/complete response', function () {
    $id = QuranText::first()->id;

    $payload = [
        'quran_text_id' => $id, 'wpm' => 30, 'raw_wpm' => 33, 'accuracy' => 92.0,
        'char_count' => 40, 'correct_chars' => 37, 'incorrect_chars' => 3,
        'mode' => 'quote', 'duration' => 20, 'start_ayah' => 1, 'end_ayah' => 1, 'total_errors' => 3,
    ];

    $first = actingAs($this->user)->postJson('/test/complete', $payload)->assertCreated()->json();
    expect(collect($first['new_badges'])->pluck('name'))->toContain('First steps');

    $second = actingAs($this->user)->postJson('/test/complete', $payload)->assertCreated()->json();
    expect($second['new_badges'])->toBe([]);
});

it('awards consistency, variety and juz-coverage badges', function () {
    $u = $this->user;

    // 30 active days, 8 of them hitting the 500-char default goal.
    for ($i = 1; $i <= 30; $i++) {
        DB::table('daily_activity')->insert([
            'user_id' => $u->id, 'date' => now()->subDays($i)->toDateString(),
            'tests_count' => 1, 'chars' => $i <= 8 ? 900 : 100, 'seconds' => 60,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // 10 zero-error runs + one 500+ char test + a hifz test.
    Test::factory()->count(10)->for($u)->create(['char_count' => 120, 'incorrect_chars' => 0]);
    Test::factory()->for($u)->create(['char_count' => 620]);
    Test::factory()->for($u)->create(['hifz_level' => 2]);

    // Certified surahs spanning 4 juz (30, 1, 3, 4 — via seeded quran_texts).
    QuranText::insert(collect([[1, 1], [2, 3], [3, 4]])->map(fn ($p): array => [
        'surah_number' => $p[0], 'ayah_number' => 1, 'juz' => $p[1], 'hizb_quarter' => 1, 'page' => 1,
        'text_arabic_simple' => 'x', 'surah_arabic_ponctuation' => 'x',
        'surah_name_arabic' => 's', 'surah_name_english' => 's', 'surah_name_translation' => 's',
        'created_at' => now(), 'updated_at' => now(),
    ])->all());
    foreach ([108, 1, 2, 3] as $s) {
        Certificate::updateOrCreate(
            ['user_id' => $u->id, 'surah_number' => $s],
            ['surah_name_english' => 's', 'surah_name_arabic' => 's', 'ayah_count' => 3, 'accuracy' => 99, 'issued_at' => now()],
        );
    }

    app(BadgeService::class)->evaluate($u->fresh());
    $slugs = $u->fresh()->badges()->pluck('slug');

    expect($slugs)->toContain('days-30', 'goal-7', 'perfectionist', 'marathon', 'first-hifz')
        ->and($slugs)->not->toContain('days-100', 'goal-30');
});

it('awards tashkeel badges from the tests.tashkeel flag', function () {
    $u = $this->user;

    Test::factory()->count(3)->for($u)->create(['tashkeel' => false]);
    Test::factory()->for($u)->create(['tashkeel' => true, 'char_count' => 120, 'incorrect_chars' => 0]);

    app(BadgeService::class)->evaluate($u->fresh());
    $slugs = $u->fresh()->badges()->pluck('slug');

    expect($slugs)->toContain('first-tashkeel', 'tashkeel-perfect')
        ->and($slugs)->not->toContain('tashkeel-25');
});

it('records the tashkeel flag on /test/complete', function () {
    $id = QuranText::first()->id;

    actingAs($this->user)->postJson('/test/complete', [
        'quran_text_id' => $id, 'wpm' => 30, 'raw_wpm' => 30, 'accuracy' => 95.0,
        'char_count' => 60, 'correct_chars' => 57, 'incorrect_chars' => 3,
        'mode' => 'quote', 'duration' => 20, 'start_ayah' => 1, 'end_ayah' => 1,
        'total_errors' => 3, 'tashkeel' => true,
    ])->assertCreated();

    expect(Test::where('user_id', $this->user->id)->value('tashkeel'))->toBeTrue();
});

it('backfills badges for existing users', function () {
    // A user with history but no badges yet (simulate a pre-feature account).
    $legacy = User::factory()->create(['longest_streak' => 40]);
    Test::factory()->count(12)->for($legacy)->create(['wpm' => 70]);
    $legacy->badges()->detach();

    expect($legacy->badges()->count())->toBe(0);

    $this->artisan('badges:backfill')->assertSuccessful();

    $slugs = $legacy->fresh()->badges()->pluck('slug');
    expect($slugs)->toContain('first-test', 'tests-10', 'wpm-60', 'streak-30');

    // Second run awards nothing more.
    $before = $legacy->fresh()->badges()->count();
    $this->artisan('badges:backfill')->assertSuccessful();
    expect($legacy->fresh()->badges()->count())->toBe($before);
});

it('guards the badges page and shows earned state', function () {
    $this->get('/badges')->assertRedirect('/login');

    Test::factory()->for($this->user)->create();
    app(BadgeService::class)->evaluate($this->user->fresh());

    actingAs($this->user)->get('/badges')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Badges/Index')
            ->has('badges', count(config('badges.list')))
            ->where('badges.0.slug', 'first-test')
            ->whereNot('badges.0.earned_at', null)
        );
});
