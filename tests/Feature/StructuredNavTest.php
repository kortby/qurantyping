<?php

use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Services\QuranNavigator;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

/** Insert a small, division-tagged slice of the Quran. */
function seedAyahs(): void
{
    $rows = [];

    $make = fn (int $s, int $a, int $juz, int $hizb, int $page, string $name): array => [
        'surah_number' => $s,
        'ayah_number' => $a,
        'juz' => $juz,
        'hizb_quarter' => $hizb,
        'page' => $page,
        'text_arabic_simple' => 'كلمة كلمة كلمة كلمة',
        'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة',
        'surah_name_arabic' => $name,
        'surah_name_english' => $name,
        'surah_name_translation' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    for ($a = 1; $a <= 7; $a++) {
        $rows[] = $make(1, $a, 1, 1, 1, 'Al-Fatihah');
    }
    for ($a = 1; $a <= 12; $a++) {
        $rows[] = $make(2, $a, 1, $a <= 6 ? 1 : 2, $a <= 5 ? 2 : 3, 'Al-Baqarah');
    }
    for ($a = 142; $a <= 152; $a++) {
        $rows[] = $make(2, $a, 2, 9, 22, 'Al-Baqarah');
    }

    QuranText::insert($rows);
}

beforeEach(function () {
    Cache::flush();
    seedAyahs();
});

/* ---- QuranNavigator ---- */

it('resolves a juz to its opening passage', function () {
    $nav = app(QuranNavigator::class);

    expect($nav->resolve('juz', 1))->toMatchArray(['surah_number' => 1, 'start_ayah' => 1, 'end_ayah' => 5]);
    expect($nav->resolve('juz', 2))->toMatchArray(['surah_number' => 2, 'start_ayah' => 142, 'end_ayah' => 146]);
});

it('resolves a mushaf page to its opening passage', function () {
    expect(app(QuranNavigator::class)->resolve('page', 3))
        ->toMatchArray(['surah_number' => 2, 'start_ayah' => 6, 'end_ayah' => 10]);
});

it('returns null for an unknown scope or empty division', function () {
    $nav = app(QuranNavigator::class);

    expect($nav->resolve('surah', 1))->toBeNull();
    expect($nav->resolve('juz', 30))->toBeNull();
});

it('finds the passage after a given ayah, rolling into the next surah', function () {
    $nav = app(QuranNavigator::class);
    $fatihaEnd = QuranText::where('surah_number', 1)->where('ayah_number', 7)->value('id');

    expect($nav->nextAfter($fatihaEnd))
        ->toMatchArray(['surah_number' => 2, 'start_ayah' => 1, 'end_ayah' => 5]);
});

it('returns null past the end of the seeded Quran', function () {
    $last = QuranText::where('surah_number', 2)->where('ayah_number', 152)->value('id');

    expect(app(QuranNavigator::class)->nextAfter($last))->toBeNull();
});

it('remembers where a completed test finished', function () {
    $user = User::factory()->create();
    $startId = QuranText::where('surah_number', 2)->where('ayah_number', 1)->value('id');

    Test::factory()->create([
        'user_id' => $user->id,
        'quran_text_id' => $startId,
        'start_ayah' => 1,
        'end_ayah' => 5,
    ]);

    $expected = QuranText::where('surah_number', 2)->where('ayah_number', 5)->value('id');
    expect($user->fresh()->last_quran_text_id)->toBe($expected);
});

it('builds a resume point from the stored pointer', function () {
    $user = User::factory()->create([
        'last_quran_text_id' => QuranText::where('surah_number', 2)->where('ayah_number', 5)->value('id'),
    ]);

    expect(app(QuranNavigator::class)->resumePoint($user->fresh()))
        ->toMatchArray(['label' => 'Al-Baqarah 6']);
});

/* ---- API ---- */

it('serves a passage by juz through the text endpoint', function () {
    $response = $this->get('/api/test/text?scope=juz&value=2');

    $response->assertOk()
        ->assertJsonPath('surah_number', 2)
        ->assertJsonPath('start_ayah', 142)
        ->assertJsonStructure(['id', 'last_quran_text_id', 'text_simple', 'end_ayah']);
});

it('serves the next passage via after=', function () {
    $fatihaEnd = QuranText::where('surah_number', 1)->where('ayah_number', 7)->value('id');

    $this->get("/api/test/text?after={$fatihaEnd}")
        ->assertOk()
        ->assertJsonPath('surah_number', 2)
        ->assertJsonPath('start_ayah', 1);
});

it('returns 409 when advancing past the end of the Quran', function () {
    $last = QuranText::where('surah_number', 2)->where('ayah_number', 152)->value('id');

    $this->getJson("/api/test/text?after={$last}")->assertStatus(409);
});

it('validates the scope parameter', function () {
    $this->getJson('/api/test/text?scope=chapter&value=1')->assertStatus(422);
    $this->getJson('/api/test/text?scope=juz&value=abc')->assertStatus(422);
});

it('exposes the juz and page index', function () {
    $this->getJson('/api/quran/scopes')
        ->assertOk()
        ->assertJsonPath('juz.0.value', 1)
        ->assertJsonPath('juz.0.surah', 'Al-Fatihah')
        ->assertJsonPath('juz.1.surah', 'Al-Baqarah');
});

it('toggles auto-advance through the settings endpoint', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/user/settings/auto-advance', ['enabled' => true])->assertRedirect();
    expect($user->fresh()->auto_advance)->toBeTrue();

    actingAs($user)->post('/user/settings/auto-advance', ['enabled' => 'nope'])->assertSessionHasErrors('enabled');
});
