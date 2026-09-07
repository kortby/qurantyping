<?php

use App\Models\QuranText;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function seedShareAyahs(): void
{
    $rows = [];
    for ($a = 1; $a <= 5; $a++) {
        $rows[] = [
            'surah_number' => 108, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 602,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'الكوثر', 'surah_name_english' => 'Al-Kawthar', 'surah_name_translation' => 'Abundance',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

function sharePayload(array $overrides = []): array
{
    return array_merge([
        'quran_text_id' => QuranText::first()->id,
        'wpm' => 42, 'raw_wpm' => 46, 'accuracy' => 97.3,
        'char_count' => 60, 'correct_chars' => 58, 'incorrect_chars' => 2,
        'mode' => 'quote', 'duration' => 30,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 2,
    ], $overrides);
}

beforeEach(fn () => seedShareAyahs());

it('returns the fresh streak after a completed test', function () {
    $user = User::factory()->create([
        'current_streak' => 0,
        'last_practiced_on' => null,
    ]);

    actingAs($user)
        ->postJson('/test/complete', sharePayload())
        ->assertCreated()
        ->assertJsonPath('streak', 1);
});

it('advances the returned streak from an existing run', function () {
    $user = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 5,
        'last_practiced_on' => now()->subDay()->toDateString(),
    ]);

    actingAs($user)
        ->postJson('/test/complete', sharePayload())
        ->assertCreated()
        ->assertJsonPath('streak', 6);
});

it('returns a null streak for guests', function () {
    postJson('/test/complete', sharePayload())
        ->assertCreated()
        ->assertJsonPath('streak', null);
});

it('exposes the english surah name for the share card', function () {
    getJson('/api/test/text?surah_number=108&start_ayah=1&end_ayah=3')
        ->assertOk()
        ->assertJsonPath('surah_name_english', 'Al-Kawthar')
        ->assertJsonPath('surah_name_arabic', 'الكوثر');
});
