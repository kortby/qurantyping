<?php

use App\Models\QuranText;
use App\Models\Race;
use App\Models\RaceParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com']);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);

    // Race::factory() needs a quran_texts row (there is no QuranTextFactory).
    QuranText::insert(collect(range(1, 4))->map(fn (int $a): array => [
        'surah_number' => 108, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 602,
        'text_arabic_simple' => 'كلمة كلمة كلمة', 'surah_arabic_ponctuation' => 'كلمة كلمة كلمة',
        'surah_name_arabic' => 'الكوثر', 'surah_name_english' => 'Al-Kawthar', 'surah_name_translation' => 'Al-Kawthar',
        'created_at' => now(), 'updated_at' => now(),
    ])->all());
});

it('redirects guests away from the races list', function () {
    $this->get('/admin/races')->assertRedirect('/login');
});

it('forbids non-admins from the races list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/races')->assertForbidden();
});

it('shows the races list to a super admin', function () {
    Race::factory()->count(3)->create();

    $this->actingAs($this->admin)->get('/admin/races')->assertOk();
});

it('lists public and friend races with their host and winner', function () {
    $host = User::factory()->create(['name' => 'Host Person']);
    $winner = User::factory()->create(['name' => 'Winner Person']);

    $public = Race::factory()->create(['surah_number' => 1, 'start_ayah' => 1, 'end_ayah' => 3]);
    RaceParticipant::factory()->finished(1)->create(['race_id' => $public->id, 'user_id' => $winner->id]);

    Race::factory()->private()->create(['host_user_id' => $host->id]);

    $this->actingAs($this->admin)->get('/admin/races')
        ->assertOk()
        ->assertSee('Host Person')
        ->assertSee('Winner Person')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Races/Index')
            ->has('races.data', 2)
            ->where('counts.all', 2)
            ->where('counts.public', 1)
            ->where('counts.private', 1)
            ->where('races.data.0.visibility', 'private')
            ->where('races.data.0.host', 'Host Person')
            ->where('races.data.1.visibility', 'public')
            ->where('races.data.1.winner', 'Winner Person')
        );
});

it('filters the races list by visibility', function () {
    Race::factory()->count(2)->create();
    Race::factory()->private()->create();

    $this->actingAs($this->admin)->get('/admin/races?visibility=private')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Races/Index')
            ->has('races.data', 1)
            ->where('races.data.0.visibility', 'private')
        );

    $this->actingAs($this->admin)->get('/admin/races?visibility=public')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('races.data', 2)
        );
});
