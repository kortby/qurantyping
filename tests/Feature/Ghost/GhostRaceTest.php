<?php

use App\Models\Friendship;
use App\Models\QuranText;
use App\Models\Result;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;

function seedGhostAyahs(): void
{
    $rows = [];
    for ($a = 1; $a <= 6; $a++) {
        $rows[] = [
            'surah_number' => 113, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 604,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'الفلق', 'surah_name_english' => 'Al-Falaq', 'surah_name_translation' => 'Al-Falaq',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

function completePayload(int $quranTextId, array $overrides = []): array
{
    return array_merge([
        'quran_text_id' => $quranTextId,
        'wpm' => 40, 'raw_wpm' => 45, 'accuracy' => 96.5,
        'char_count' => 50, 'correct_chars' => 48, 'incorrect_chars' => 2,
        'mode' => 'quote', 'duration' => 30,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 2,
    ], $overrides);
}

beforeEach(fn () => seedGhostAyahs());

it('returns a friend\'s recorded trace', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $friend->id, 'friend_id' => $me->id]);

    $test = Test::factory()->for($friend)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $test->id, 'history' => [[0, 0], [1000, 20], [2000, 45]]]);

    actingAs($me)->getJson("/ghost/{$test->id}")
        ->assertOk()
        ->assertJsonPath('opponent.name', $friend->name)
        ->assertJsonPath('opponent.is_self', false)
        ->assertJsonPath('trace.2.1', 45);
});

it('forbids racing a stranger\'s ghost', function () {
    $me = User::factory()->create();
    $stranger = User::factory()->create();

    $test = Test::factory()->for($stranger)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $test->id, 'history' => [[0, 0]]]);

    actingAs($me)->getJson("/ghost/{$test->id}")->assertForbidden();
});

it('404s when a friend\'s test has no recorded trace', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $me->id, 'friend_id' => $friend->id]);

    $test = Test::factory()->for($friend)->create(['quran_text_id' => QuranText::first()->id]);

    actingAs($me)->getJson("/ghost/{$test->id}")->assertNotFound();
});

it('serves the caller\'s newest recorded run for /ghost/pb', function () {
    $me = User::factory()->create();
    $qt = QuranText::first()->id;

    $old = Test::factory()->for($me)->create(['quran_text_id' => $qt, 'created_at' => now()->subDay()]);
    Result::create(['test_id' => $old->id, 'history' => [[0, 0], [900, 30]]]);
    $new = Test::factory()->for($me)->create(['quran_text_id' => $qt]);
    Result::create(['test_id' => $new->id, 'history' => [[0, 0], [800, 33]]]);

    actingAs($me)->getJson('/ghost/pb')
        ->assertOk()
        ->assertJsonPath('opponent.is_self', true)
        ->assertJsonPath('trace.1.1', 33);
});

it('404s on /ghost/pb when the caller has no recorded run', function () {
    actingAs(User::factory()->create())->getJson('/ghost/pb')->assertNotFound();
});

it('persists a trace to the results table on POST /test/complete', function () {
    $me = User::factory()->create();

    $response = actingAs($me)->postJson('/test/complete', completePayload(QuranText::first()->id, [
        'trace' => [[0, 0], [1200, 25], [2400, 50]],
    ]))->assertCreated();

    $test = Test::where('user_id', $me->id)->latest()->first();

    expect($test->result)->not->toBeNull()
        ->and($test->result->history)->toBe([[0, 0], [1200, 25], [2400, 50]]);

    expect($response->json('challenge_url'))->toContain('/challenge/'.$test->id)
        ->and($response->json('challenge_url'))->toContain('signature=');
});

it('rejects an unsigned challenge link', function () {
    $owner = User::factory()->create();
    $test = Test::factory()->for($owner)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $test->id, 'history' => [[0, 0], [500, 10]]]);

    actingAs(User::factory()->create())->get("/challenge/{$test->id}")->assertForbidden();
});

it('grants a non-friend a one-visit ghost pass through a signed challenge link', function () {
    $me = User::factory()->create();
    $owner = User::factory()->create();
    $test = Test::factory()->for($owner)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $test->id, 'history' => [[0, 0], [500, 10], [1000, 22]]]);

    $signed = URL::signedRoute('challenge.show', ['test' => $test->id]);

    actingAs($me)->get($signed)->assertRedirect('/?ghost='.$test->id);

    actingAs($me)->getJson("/ghost/{$test->id}")
        ->assertOk()
        ->assertJsonPath('opponent.is_friend', false)
        ->assertJsonPath('trace.2.1', 22);
});

it('creates no result row when no trace is sent', function () {
    $me = User::factory()->create();

    actingAs($me)->postJson('/test/complete', completePayload(QuranText::first()->id))
        ->assertCreated();

    expect(Result::count())->toBe(0);
});

it('rejects a trace longer than 600 samples', function () {
    $me = User::factory()->create();

    actingAs($me)->postJson('/test/complete', completePayload(QuranText::first()->id, [
        'trace' => array_fill(0, 700, [0, 0]),
    ]))->assertUnprocessable();
});
