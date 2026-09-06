<?php

use App\Models\QuranText;
use App\Models\User;
use App\Models\UserAyahCompletion;
use App\Models\UserAyahProgress;
use App\Services\QuranMapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;

function seedMapAyahs(): void
{
    $rows = [];
    $make = fn (int $s, int $a, int $juz, string $en, string $ar): array => [
        'surah_number' => $s, 'ayah_number' => $a, 'juz' => $juz, 'hizb_quarter' => 1, 'page' => 1,
        'text_arabic_simple' => 'كلمة كلمة كلمة',
        'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
        'surah_name_arabic' => $ar, 'surah_name_english' => $en, 'surah_name_translation' => $en,
        'created_at' => now(), 'updated_at' => now(),
    ];
    for ($a = 1; $a <= 3; $a++) {
        $rows[] = $make(103, $a, 30, 'Al-Asr', 'العصر');
    }
    for ($a = 1; $a <= 5; $a++) {
        $rows[] = $make(105, $a, 30, 'Al-Fil', 'الفيل');
    }
    for ($a = 1; $a <= 4; $a++) {
        $rows[] = $make(1, $a, 1, 'Al-Fatihah', 'الفاتحة');
    }
    // A surah that straddles two juz (like Al-Baqarah spanning 1-3).
    for ($a = 1; $a <= 6; $a++) {
        $rows[] = $make(2, $a, $a <= 4 ? 1 : 2, 'Al-Baqarah', 'البقرة');
    }
    QuranText::insert($rows);
}

beforeEach(function () {
    Config::set('hifz.certificate_accuracy', 95);
    Cache::flush();
    seedMapAyahs();
    $this->user = User::factory()->create();
});

function completion(int $userId, int $surah, int $ayah, float $acc): void
{
    $id = QuranText::where('surah_number', $surah)->where('ayah_number', $ayah)->value('id');
    UserAyahCompletion::create(['user_id' => $userId, 'quran_text_id' => $id, 'best_accuracy' => $acc, 'attempts' => 1]);
}

function memorise(int $userId, int $surah, int $ayah): void
{
    $id = QuranText::where('surah_number', $surah)->where('ayah_number', $ayah)->value('id');
    UserAyahProgress::create([
        'user_id' => $userId, 'quran_text_id' => $id, 'status' => 'review',
        'ease' => 2.5, 'interval_days' => 3, 'reps' => 2, 'lapses' => 0, 'due_on' => now()->addDays(3),
    ]);
}

it('aggregates practised / mastered / memorised per surah and totals', function () {
    // Al-Asr: 2 practised (one <95, one =97), of which 1 mastered; 1 memorised.
    completion($this->user->id, 103, 1, 80.0);
    completion($this->user->id, 103, 2, 97.0);
    memorise($this->user->id, 103, 3);
    // Al-Fil: 1 mastered.
    completion($this->user->id, 105, 1, 99.0);

    $map = app(QuranMapService::class)->overview($this->user);

    $asr = collect($map['surahs'])->firstWhere('number', 103);
    expect($asr['practiced'])->toBe(2)
        ->and($asr['mastered'])->toBe(1)
        ->and($asr['memorised'])->toBe(1)
        ->and($asr['ayah_count'])->toBe(3);

    $fil = collect($map['surahs'])->firstWhere('number', 105);
    expect($fil['practiced'])->toBe(1)->and($fil['mastered'])->toBe(1);

    expect($map['totals']['practiced'])->toBe(3)
        ->and($map['totals']['mastered'])->toBe(2)
        ->and($map['totals']['memorised'])->toBe(1)
        ->and($map['totals']['ayah_count'])->toBe(18);

    $juz30 = collect($map['juz'])->firstWhere('juz', 30);
    expect($juz30['practiced'])->toBe(3)->and($juz30['memorised'])->toBe(1)->and($juz30['ayah_count'])->toBe(8);
});

it('counts juz ayah_count from the per-ayah juz column, not the surah rollup', function () {
    $map = app(QuranMapService::class)->overview($this->user);

    // Surah 2 puts 4 ayahs in juz 1 and 2 ayahs in juz 2; surah 1 adds 4 to juz 1.
    expect(collect($map['juz'])->firstWhere('juz', 1)['ayah_count'])->toBe(8)
        ->and(collect($map['juz'])->firstWhere('juz', 2)['ayah_count'])->toBe(2);
});

it('reports per-ayah state with the highest layer winning', function () {
    completion($this->user->id, 105, 1, 60.0);   // practised
    completion($this->user->id, 105, 2, 96.0);   // mastered
    completion($this->user->id, 105, 3, 99.0);
    memorise($this->user->id, 105, 3);           // mastered + memorised -> memorised

    $detail = app(QuranMapService::class)->surahDetail($this->user, 105);

    expect($detail['ayahs'])->toHaveCount(5);
    $byN = collect($detail['ayahs'])->keyBy('n');
    expect($byN[1]['state'])->toBe('practiced')
        ->and($byN[2]['state'])->toBe('mastered')
        ->and($byN[3]['state'])->toBe('memorised')
        ->and($byN[4]['state'])->toBe('untouched');
});

it('guards the map behind auth', function () {
    $this->get('/map')->assertRedirect('/login');
});

it('renders the map page and the surah endpoint', function () {
    completion($this->user->id, 1, 1, 98.0);

    actingAs($this->user)->get('/map')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Map/Index')
            ->has('map.surahs')
            ->where('map.totals.mastered', 1)
        );

    actingAs($this->user)->getJson('/map/1')
        ->assertOk()
        ->assertJsonPath('surah_number', 1)
        ->assertJsonCount(4, 'ayahs');

    actingAs($this->user)->getJson('/map/200')->assertNotFound();
});
