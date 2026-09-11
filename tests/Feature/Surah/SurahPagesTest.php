<?php

use App\Models\QuranText;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $rows = [];
    for ($a = 1; $a <= 3; $a++) {
        $rows[] = [
            'surah_number' => 112, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 604,
            'text_arabic_simple' => 'كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
            'surah_name_arabic' => 'الإخلاص', 'surah_name_english' => 'Al-Ikhlaas', 'surah_name_translation' => 'Sincerity',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    for ($a = 1; $a <= 30; $a++) {
        $rows[] = [
            'surah_number' => 67, 'ayah_number' => $a, 'juz' => 29, 'hizb_quarter' => 230, 'page' => 562,
            'text_arabic_simple' => 'كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
            'surah_name_arabic' => 'الملك', 'surah_name_english' => 'Al-Mulk', 'surah_name_translation' => 'The Sovereignty',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
});

it('lists every surah on the index page', function () {
    $this->get('/surah')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('surahs', 2)
            ->where('surahs.0.name_english', 'Al-Mulk')
            ->where('surahs.0.ayah_count', 30)
        );
});

it('serves a surah landing page with its own SEO title and FAQ', function () {
    $this->get('/surah/al-mulk')
        ->assertOk()
        ->assertSee('Surah Al-Mulk — Type &amp; Memorize Online | QuranTyping', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('Al-Mulk (الملك) has 30 verses', false);
});

it('404s on an unknown surah slug', function () {
    $this->get('/surah/not-a-real-surah')->assertNotFound();
});

it('includes every surah landing page in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertSee(url('/surah'), false)
        ->assertSee(url('/surah/al-mulk'), false)
        ->assertSee(url('/surah/al-ikhlaas'), false);
});
