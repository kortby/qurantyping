<?php

use App\Models\Friendship;
use App\Models\QuranText;
use App\Models\Result;
use App\Models\Test;
use App\Models\User;

use function Pest\Laravel\actingAs;

function seedNotifAyahs(): void
{
    $rows = [];
    for ($a = 1; $a <= 6; $a++) {
        $rows[] = [
            'surah_number' => 110, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 603,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'النصر', 'surah_name_english' => 'An-Nasr', 'surah_name_translation' => 'Divine Support',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

it('notifies the recipient of a new friend request', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();

    actingAs($me)->post('/friends', ['friend_id' => $them->id]);

    expect($them->notifications)->toHaveCount(1)
        ->and($them->notifications->first()->data['type'])->toBe('friend_request')
        ->and($them->notifications->first()->data['actor_name'])->toBe($me->name);
});

it('notifies through an invite link too', function () {
    $owner = User::factory()->create();
    $token = $owner->inviteToken();
    $visitor = User::factory()->create();

    actingAs($visitor)->get("/i/{$token}");

    expect($owner->notifications->first()?->data['type'])->toBe('friend_request');
});

it('notifies the requester when their request is accepted', function () {
    $requester = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['user_id' => $requester->id, 'friend_id' => $recipient->id]);

    actingAs($recipient)->patch("/friends/{$friendship->id}");

    expect($requester->notifications->first()?->data['type'])->toBe('friend_accepted')
        ->and($requester->notifications->first()->data['actor_name'])->toBe($recipient->name);
});

it('notifies the original requester on a reverse-pending auto-accept', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    Friendship::factory()->create(['user_id' => $first->id, 'friend_id' => $second->id]);

    actingAs($second)->post('/friends', ['friend_id' => $first->id]);

    expect($first->notifications->first()?->data['type'])->toBe('friend_accepted');
});

it('notifies the run owner when a friend races their ghost', function () {
    seedNotifAyahs();
    $owner = User::factory()->create();
    $racer = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $owner->id, 'friend_id' => $racer->id]);

    $ghostTest = Test::factory()->for($owner)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $ghostTest->id, 'history' => [[0, 0], [2000, 40]]]);

    actingAs($racer)->postJson('/test/complete', [
        'quran_text_id' => QuranText::first()->id,
        'wpm' => 55, 'raw_wpm' => 60, 'accuracy' => 97,
        'char_count' => 40, 'correct_chars' => 39, 'incorrect_chars' => 1,
        'mode' => 'quote', 'duration' => 12,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 1,
        'ghost_of' => $ghostTest->id,
        'ghost_beat' => true,
    ])->assertCreated();

    expect($owner->notifications->first()?->data['type'])->toBe('ghost_won')
        ->and($owner->notifications->first()->data['actor_name'])->toBe($racer->name)
        ->and($owner->notifications->first()->data['url'])->toContain('ghost=');
});

it('records a plain ghost_raced type when the ghost was not beaten', function () {
    seedNotifAyahs();
    $owner = User::factory()->create();
    $racer = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $owner->id, 'friend_id' => $racer->id]);

    $ghostTest = Test::factory()->for($owner)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $ghostTest->id, 'history' => [[0, 0], [2000, 40]]]);

    actingAs($racer)->postJson('/test/complete', [
        'quran_text_id' => QuranText::first()->id,
        'wpm' => 30, 'raw_wpm' => 33, 'accuracy' => 95,
        'char_count' => 40, 'correct_chars' => 38, 'incorrect_chars' => 2,
        'mode' => 'quote', 'duration' => 40,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 2,
        'ghost_of' => $ghostTest->id,
        'ghost_beat' => false,
    ])->assertCreated();

    expect($owner->notifications->first()?->data['type'])->toBe('ghost_raced');
});

it('does not notify when racing your own pb', function () {
    seedNotifAyahs();
    $me = User::factory()->create();

    $ghostTest = Test::factory()->for($me)->create(['quran_text_id' => QuranText::first()->id]);
    Result::create(['test_id' => $ghostTest->id, 'history' => [[0, 0], [2000, 40]]]);

    actingAs($me)->postJson('/test/complete', [
        'quran_text_id' => QuranText::first()->id,
        'wpm' => 50, 'raw_wpm' => 55, 'accuracy' => 98,
        'char_count' => 40, 'correct_chars' => 40, 'incorrect_chars' => 0,
        'mode' => 'quote', 'duration' => 15,
        'start_ayah' => 1, 'end_ayah' => 3, 'total_errors' => 0,
        'ghost_of' => $ghostTest->id,
        'ghost_beat' => true,
    ])->assertCreated();

    expect($me->notifications)->toHaveCount(0);
});

it('marks all notifications read', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    actingAs($them)->post('/friends', ['friend_id' => $me->id]);

    expect($me->fresh()->unreadNotifications)->toHaveCount(1);

    actingAs($me)->postJson('/notifications/read')->assertOk();

    expect($me->fresh()->unreadNotifications)->toHaveCount(0);
});

it('shares the unread count and recent notifications with the frontend', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    actingAs($them)->post('/friends', ['friend_id' => $me->id]);

    actingAs($me)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('auth.unread_count', 1)
            ->where('auth.notifications.0.type', 'friend_request')
        );
});
