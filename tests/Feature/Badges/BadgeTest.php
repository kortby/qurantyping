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
