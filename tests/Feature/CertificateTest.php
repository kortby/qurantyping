<?php

use App\Models\Certificate;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahCompletion;
use App\Services\CertificateService;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;

function seedCertAyahs(): void
{
    $rows = [];
    $make = fn (int $s, int $a, string $en, string $ar): array => [
        'surah_number' => $s, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 600,
        'text_arabic_simple' => 'كلمة كلمة كلمة كلمة',
        'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة',
        'surah_name_arabic' => $ar, 'surah_name_english' => $en, 'surah_name_translation' => $en,
        'created_at' => now(), 'updated_at' => now(),
    ];
    for ($a = 1; $a <= 3; $a++) {
        $rows[] = $make(103, $a, 'Al-Asr', 'العصر');
    }
    for ($a = 1; $a <= 5; $a++) {
        $rows[] = $make(105, $a, 'Al-Fil', 'الفيل');
    }
    QuranText::insert($rows);
}

beforeEach(function () {
    Config::set('hifz.certificate_accuracy', 95);
    seedCertAyahs();
});

function completeSurah(User $user, int $surah, float $accuracy, ?int $endAyah = null): Test
{
    $count = QuranText::where('surah_number', $surah)->count();
    $firstId = QuranText::where('surah_number', $surah)->orderBy('ayah_number')->value('id');

    return Test::factory()->create([
        'user_id' => $user->id,
        'quran_text_id' => $firstId,
        'start_ayah' => 1,
        'end_ayah' => $endAyah ?? $count,
        'accuracy' => $accuracy,
    ]);
}

it('issues a certificate once every ayah is at the bar', function () {
    $user = User::factory()->create();

    completeSurah($user, 103, 97.0);

    $cert = Certificate::where('user_id', $user->id)->where('surah_number', 103)->first();

    expect($cert)->not->toBeNull()
        ->and($cert->ayah_count)->toBe(3)
        ->and((float) $cert->accuracy)->toBe(97.0)
        ->and($cert->surah_name_english)->toBe('Al-Asr');
});

it('does not issue while any ayah is below the bar', function () {
    $user = User::factory()->create();

    completeSurah($user, 103, 90.0);

    expect(Certificate::where('user_id', $user->id)->count())->toBe(0);
    expect(UserAyahCompletion::where('user_id', $user->id)->count())->toBe(3);
});

it('does not issue when only part of the surah is covered', function () {
    $user = User::factory()->create();

    completeSurah($user, 105, 99.0, endAyah: 3); // 3 of 5 ayahs

    expect(Certificate::where('user_id', $user->id)->count())->toBe(0);
});

it('accumulates the best accuracy per ayah across tests', function () {
    $user = User::factory()->create();

    completeSurah($user, 103, 88.0); // below bar
    expect(Certificate::where('user_id', $user->id)->count())->toBe(0);

    completeSurah($user, 103, 96.0); // now every ayah's best >= 95
    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});

it('never issues a second certificate for the same surah', function () {
    $user = User::factory()->create();

    completeSurah($user, 103, 96.0);
    completeSurah($user, 103, 99.5);

    expect(Certificate::where('user_id', $user->id)->where('surah_number', 103)->count())->toBe(1);
});

it('keeps an issued certificate after the accuracy bar is raised', function () {
    $user = User::factory()->create();
    completeSurah($user, 103, 96.0);

    Config::set('hifz.certificate_accuracy', 99);
    completeSurah($user, 103, 96.0); // would not qualify under the new bar

    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});

it('returns null from checkSurah with no completions', function () {
    $user = User::factory()->create();

    expect(app(CertificateService::class)->checkSurah($user, 103))->toBeNull();
});

it('lists only the viewer\'s certificates', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    completeSurah($me, 103, 97.0);
    completeSurah($other, 105, 97.0);

    $response = actingAs($me)->get('/certificates');

    $response->assertOk()->assertSee('Al-Asr')->assertDontSee('Al-Fil');
});

it('assigns a share token to every issued certificate', function () {
    $user = User::factory()->create();
    completeSurah($user, 103, 97.0);

    $cert = Certificate::where('user_id', $user->id)->firstOrFail();

    expect($cert->share_token)->toBeString()->toHaveLength(10);
});

it('exposes the public share url on the certificates page', function () {
    $user = User::factory()->create();
    completeSurah($user, 103, 97.0);
    $cert = Certificate::where('user_id', $user->id)->firstOrFail();

    actingAs($user)->get('/certificates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('certificates.0.share_url', url('/c/'.$cert->share_token)));
});

it('serves a certificate publicly to guests via its share token', function () {
    $owner = User::factory()->create(['name' => 'Aisha Rahman']);
    completeSurah($owner, 103, 97.0);
    $cert = Certificate::where('user_id', $owner->id)->firstOrFail();

    $this->get('/c/'.$cert->share_token)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Certificates/Show')
            ->where('certificate.holder', 'Aisha Rahman')
            ->where('certificate.surah_name_english', 'Al-Asr'));
});

it('404s on an unknown share token', function () {
    $this->get('/c/nope-nope-nope')->assertNotFound();
});

it('renders social share meta tags on the public certificate page', function () {
    $owner = User::factory()->create(['name' => 'Bilal Khan']);
    completeSurah($owner, 103, 97.0);
    $cert = Certificate::where('user_id', $owner->id)->firstOrFail();

    $this->get('/c/'.$cert->share_token)
        ->assertOk()
        ->assertSee('og:title', false)
        ->assertSee('Bilal Khan completed Surah Al-Asr', false)
        ->assertSee('images/certificate-og.png', false)
        ->assertSee('twitter:card', false);
});
