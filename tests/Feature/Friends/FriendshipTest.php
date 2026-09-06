<?php

use App\Models\Friendship;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;

use function Pest\Laravel\actingAs;

function seedFriendAyahs(): void
{
    $rows = [];
    for ($a = 1; $a <= 6; $a++) {
        $rows[] = [
            'surah_number' => 112, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 604,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'الإخلاص', 'surah_name_english' => 'Al-Ikhlas', 'surah_name_translation' => 'Sincerity',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

it('creates a pending request on POST /friends', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();

    actingAs($me)->post('/friends', ['friend_id' => $them->id])->assertRedirect();

    $this->assertDatabaseHas('friendships', [
        'user_id' => $me->id, 'friend_id' => $them->id, 'status' => 'pending',
    ]);
});

it('auto-accepts when the target already has a pending request to me', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    Friendship::factory()->create(['user_id' => $them->id, 'friend_id' => $me->id]);

    actingAs($me)->post('/friends', ['friend_id' => $them->id])->assertRedirect();

    expect(Friendship::count())->toBe(1);
    $this->assertDatabaseHas('friendships', [
        'user_id' => $them->id, 'friend_id' => $me->id, 'status' => 'accepted',
    ]);
});

it('rejects a friend request to yourself', function () {
    $me = User::factory()->create();

    actingAs($me)->post('/friends', ['friend_id' => $me->id]);

    expect(Friendship::count())->toBe(0);
});

it('does not create a duplicate request in either direction', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    Friendship::factory()->create(['user_id' => $me->id, 'friend_id' => $them->id]);

    actingAs($me)->post('/friends', ['friend_id' => $them->id]);
    actingAs($them)->post('/friends', ['friend_id' => $me->id]);

    expect(Friendship::count())->toBe(1);
});

it('lets only the recipient accept a pending request', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    $stranger = User::factory()->create();
    $friendship = Friendship::factory()->create(['user_id' => $them->id, 'friend_id' => $me->id]);

    actingAs($stranger)->patch("/friends/{$friendship->id}")->assertForbidden();
    actingAs($me)->patch("/friends/{$friendship->id}")->assertRedirect();

    expect($friendship->fresh()->status)->toBe('accepted');
    expect($friendship->fresh()->accepted_at)->not->toBeNull();
});

it('lets either party remove the friendship', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['user_id' => $them->id, 'friend_id' => $me->id]);

    actingAs($me)->delete("/friends/{$friendship->id}")->assertRedirect();

    $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
});

it('resolves friendIds in both directions and ignores pending rows', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();

    Friendship::factory()->accepted()->create(['user_id' => $me->id, 'friend_id' => $a->id]);
    Friendship::factory()->accepted()->create(['user_id' => $b->id, 'friend_id' => $me->id]);
    Friendship::factory()->create(['user_id' => $me->id, 'friend_id' => $c->id]);

    expect($me->friendIds()->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    expect($me->isFriendsWith($a))->toBeTrue();
    expect($me->isFriendsWith($c))->toBeFalse();
});

it('renders the friends page for a signed-in user', function () {
    actingAs(User::factory()->create())->get('/friends')->assertOk();
});

it('scopes the leaderboard to friends plus self', function () {
    seedFriendAyahs();

    $me = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $me->id, 'friend_id' => $friend->id]);

    $qt = QuranText::first();
    Test::factory()->for($me)->create(['quran_text_id' => $qt->id, 'wpm' => 60]);
    Test::factory()->for($friend)->create(['quran_text_id' => $qt->id, 'wpm' => 80]);
    Test::factory()->for($stranger)->create(['quran_text_id' => $qt->id, 'wpm' => 200]);

    actingAs($me)->get('/leaderboard?scope=friends')
        ->assertOk()
        ->assertInertia(function ($page) use ($me, $friend, $stranger) {
            $ids = collect($page->toArray()['props']['topScorers'])->pluck('user_id')->all();

            expect($ids)->toHaveCount(2)
                ->and($ids)->toContain($me->id, $friend->id)
                ->and($ids)->not->toContain($stranger->id);
        });
});

it('falls back to the global leaderboard for a guest asking for the friends scope', function () {
    seedFriendAyahs();
    $qt = QuranText::first();
    Test::factory()->for(User::factory())->create(['quran_text_id' => $qt->id, 'wpm' => 90]);

    $this->get('/leaderboard?scope=friends')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scope', 'global'));
});
