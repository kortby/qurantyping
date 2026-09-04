<?php

use App\Models\DailyActivity;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Services\StreakService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    QuranText::create([
        'surah_number' => 1,
        'ayah_number' => 1,
        'text_arabic_simple' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ',
        'surah_name_arabic' => 'الفاتحة',
        'surah_name_english' => 'Al-Fatihah',
        'surah_name_translation' => 'The Opening',
    ]);
});

afterEach(fn () => travelBack());

/** Create a completed test on the frozen "now" for the given user. */
function completeTest(User $user, array $attributes = []): Test
{
    return Test::factory()->create(array_merge(['user_id' => $user->id], $attributes));
}

it('records a completed test into today\'s activity', function () {
    $user = User::factory()->create();
    travelTo('2026-05-01 10:00:00');

    completeTest($user, ['char_count' => 300, 'duration' => 60]);

    $row = DailyActivity::where('user_id', $user->id)->where('date', '2026-05-01')->first();

    expect($row)->not->toBeNull()
        ->and($row->tests_count)->toBe(1)
        ->and($row->chars)->toBe(300)
        ->and($row->seconds)->toBe(60);

    expect($user->fresh())->current_streak->toBe(1)->longest_streak->toBe(1);
});

it('accumulates a second test the same day without re-counting the streak', function () {
    $user = User::factory()->create();
    travelTo('2026-05-01 10:00:00');

    completeTest($user, ['char_count' => 100, 'duration' => 20]);
    completeTest($user, ['char_count' => 150, 'duration' => 30]);

    $row = DailyActivity::firstWhere(['user_id' => $user->id, 'date' => '2026-05-01']);

    expect($row->tests_count)->toBe(2)->and($row->chars)->toBe(250)->and($row->seconds)->toBe(50);
    expect($user->fresh()->current_streak)->toBe(1);
});

it('increments the streak on consecutive days', function () {
    $user = User::factory()->create();

    foreach (['2026-05-01', '2026-05-02', '2026-05-03'] as $day) {
        travelTo("$day 09:00:00");
        completeTest($user);
    }

    expect($user->fresh())->current_streak->toBe(3)->longest_streak->toBe(3);
});

it('survives one missed day per week via the grace day', function () {
    $user = User::factory()->create();

    travelTo('2026-05-01 09:00:00');
    completeTest($user);
    travelTo('2026-05-02 09:00:00');
    completeTest($user);
    // 2026-05-03 skipped
    travelTo('2026-05-04 09:00:00');
    completeTest($user);

    $user->refresh();
    expect($user->current_streak)->toBe(3);
    expect($user->streak_grace_used_on->toDateString())->toBe('2026-05-04');
});

it('resets when two consecutive days are missed', function () {
    $user = User::factory()->create();

    travelTo('2026-05-01 09:00:00');
    completeTest($user);
    travelTo('2026-05-02 09:00:00');
    completeTest($user);
    // 2026-05-03 and 2026-05-04 skipped
    travelTo('2026-05-05 09:00:00');
    completeTest($user);

    expect($user->fresh()->current_streak)->toBe(1);
});

it('resets when the weekly grace day is already spent', function () {
    $user = User::factory()->create();

    travelTo('2026-05-01 09:00:00');
    completeTest($user);              // streak 1
    travelTo('2026-05-03 09:00:00');
    completeTest($user);              // grace used -> streak 2
    travelTo('2026-05-05 09:00:00');
    completeTest($user);              // gap again, grace spent -> streak 1

    expect($user->fresh()->current_streak)->toBe(1);
});

it('keeps the longest streak after a reset', function () {
    $user = User::factory()->create();

    foreach (['2026-05-01', '2026-05-02', '2026-05-03'] as $day) {
        travelTo("$day 09:00:00");
        completeTest($user);
    }
    travelTo('2026-05-20 09:00:00');
    completeTest($user);

    expect($user->fresh())->current_streak->toBe(1)->longest_streak->toBe(3);
});

it('ignores guest tests', function () {
    travelTo('2026-05-01 09:00:00');

    Test::factory()->create(['user_id' => null]);

    expect(DailyActivity::count())->toBe(0);
});

it('reports an effective streak of zero after lapsing without practice', function () {
    $user = User::factory()->create();

    travelTo('2026-05-01 09:00:00');
    completeTest($user);
    expect($user->fresh()->current_streak)->toBe(1);

    travelTo('2026-05-10 09:00:00');
    $snapshot = app(StreakService::class)->forInertia($user->fresh());

    expect($snapshot['current'])->toBe(0)
        ->and($snapshot['practiced_today'])->toBeFalse();
});

it('reflects daily-goal progress in the shared snapshot', function () {
    $user = User::factory()->create(['daily_goal_chars' => 200]);

    travelTo('2026-05-01 09:00:00');
    completeTest($user, ['char_count' => 120]);
    completeTest($user, ['char_count' => 150]);

    $goal = app(StreakService::class)->forInertia($user->fresh())['goal'];

    expect($goal)
        ->target->toBe(200)
        ->chars_today->toBe(270)
        ->tests_today->toBe(2)
        ->met->toBeTrue();
});

it('updates the daily goal through the settings endpoint', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/user/settings/daily-goal', ['daily_goal_chars' => 800])->assertRedirect();
    expect($user->fresh()->daily_goal_chars)->toBe(800);

    actingAs($user)->post('/user/settings/daily-goal', ['daily_goal_chars' => 5])->assertSessionHasErrors('daily_goal_chars');
});
