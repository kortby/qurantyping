<?php

use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahCompletion;

beforeEach(function () {
    QuranText::insert(collect(range(1, 6))->map(fn (int $a): array => [
        'surah_number' => 103, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 601,
        'text_arabic_simple' => 'كلمة كلمة كلمة', 'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
        'surah_name_arabic' => 'العصر', 'surah_name_english' => 'Al-Asr', 'surah_name_translation' => 'Al-Asr',
        'created_at' => now(), 'updated_at' => now(),
    ])->all());

    $this->user = User::factory()->create();
});

it('rebuilds completion rows from historical tests', function () {
    $first = QuranText::where('surah_number', 103)->orderBy('ayah_number')->value('id');

    // Two overlapping tests over ayahs 1-3 and 2-4, different accuracies.
    Test::factory()->for($this->user)->create(['quran_text_id' => $first, 'start_ayah' => 1, 'end_ayah' => 3, 'accuracy' => 80]);
    Test::factory()->for($this->user)->create(['quran_text_id' => $first, 'start_ayah' => 2, 'end_ayah' => 4, 'accuracy' => 96]);

    // Simulate the pre-feature gap: wipe what the observer wrote.
    UserAyahCompletion::where('user_id', $this->user->id)->delete();

    $this->artisan('progress:rebuild', ['--user' => $this->user->id, '--no-certs' => true])->assertSuccessful();

    // Ayahs 1-4 covered; ayah 1 once at 80, ayah 2/3 twice (max 96), ayah 4 once at 96.
    expect(UserAyahCompletion::where('user_id', $this->user->id)->count())->toBe(4);

    $byAyah = UserAyahCompletion::where('user_id', $this->user->id)
        ->join('quran_texts', 'quran_texts.id', '=', 'user_ayah_completions.quran_text_id')
        ->pluck('user_ayah_completions.best_accuracy', 'quran_texts.ayah_number');

    expect((float) $byAyah[1])->toBe(80.0)
        ->and((float) $byAyah[2])->toBe(96.0)
        ->and((float) $byAyah[4])->toBe(96.0);

    $attempts = UserAyahCompletion::where('user_id', $this->user->id)
        ->join('quran_texts', 'quran_texts.id', '=', 'user_ayah_completions.quran_text_id')
        ->pluck('user_ayah_completions.attempts', 'quran_texts.ayah_number');

    expect((int) $attempts[2])->toBe(2)->and((int) $attempts[1])->toBe(1);
});

it('is idempotent', function () {
    $first = QuranText::where('surah_number', 103)->orderBy('ayah_number')->value('id');
    Test::factory()->for($this->user)->create(['quran_text_id' => $first, 'start_ayah' => 1, 'end_ayah' => 5, 'accuracy' => 90]);

    $this->artisan('progress:rebuild', ['--no-certs' => true])->assertSuccessful();
    $this->artisan('progress:rebuild', ['--no-certs' => true])->assertSuccessful();

    expect(UserAyahCompletion::where('user_id', $this->user->id)->count())->toBe(5)
        ->and((int) UserAyahCompletion::where('user_id', $this->user->id)->max('attempts'))->toBe(1);
});
