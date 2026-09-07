<?php

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('serves an XML sitemap listing the public pages', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml');

    $response->assertSee(url('/'), false)
        ->assertSee(url('/leaderboard'), false)
        ->assertSee(url('/contest'), false)
        ->assertSee(url('/privacy-policy'), false);
});

it('lists public certificate links in the sitemap', function () {
    $certificate = Certificate::create([
        'user_id' => User::factory()->create()->id,
        'surah_number' => 108,
        'surah_name_english' => 'Al-Kawthar',
        'surah_name_arabic' => 'الكوثر',
        'ayah_count' => 3,
        'accuracy' => 98.0,
        'issued_at' => now(),
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(url('/c/'.$certificate->share_token), false);
});

it('keeps auth-gated pages out of the sitemap', function () {
    $body = $this->get('/sitemap.xml')->getContent();

    expect($body)->not->toContain('<loc>'.url('/dashboard').'</loc>')
        ->and($body)->not->toContain('<loc>'.url('/hifz').'</loc>')
        ->and($body)->not->toContain('<loc>'.url('/certificates').'</loc>');
});

it('renders WebApplication and FAQ structured data on the landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('"@type":"WebApplication"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('Is QuranTyping free?', false);
});

it('gives the landing page its own SEO title and description', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee('<title>', false)
        ->assertSee("Type the Qur'an Online — Free Arabic Typing Practice & Hifz | QuranTyping")
        ->assertSee('<meta name="description" content="Practise typing the Qur', false);
});

it('gives the leaderboard its own title tag', function () {
    $this->get('/leaderboard')
        ->assertOk()
        ->assertSee('Quran Typing Leaderboard — Fastest Arabic Typists | QuranTyping');
});
