<?php

use App\Models\ChatMessage;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com']);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

it('redirects guests away from the chats list and transcripts', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->get('/admin/chats')->assertRedirect('/login');
    $this->get("/admin/chats/{$a->id}/{$b->id}")->assertRedirect('/login');
});

it('forbids non-admins from reading chats', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->get('/admin/chats')->assertForbidden();
    $this->actingAs($a)->get("/admin/chats/{$a->id}/{$b->id}")->assertForbidden();
});

it('lists each pair of users once, newest conversation first', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $carol = User::factory()->create(['name' => 'Carol']);

    ChatMessage::factory()->create(['sender_id' => $alice->id, 'recipient_id' => $bob->id]);
    ChatMessage::factory()->create(['sender_id' => $bob->id, 'recipient_id' => $alice->id, 'body' => 'latest ab']);
    ChatMessage::factory()->create(['sender_id' => $carol->id, 'recipient_id' => $alice->id, 'body' => 'latest ac']);
    ChatMessage::factory()->create(['sender_id' => $bob->id, 'recipient_id' => $carol->id]);

    $this->actingAs($this->admin)->get('/admin/chats')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Chats/Index')
            ->where('total', 4)
            ->has('conversations.data', 3)
            ->where('conversations.data.1.last_message.body', 'latest ac')
            ->where('conversations.data.2.messages_count', 2)
            ->where('conversations.data.2.last_message.body', 'latest ab'));
});

it('filters the chats list to one user', function () {
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    ChatMessage::factory()->create(['sender_id' => $alice->id, 'recipient_id' => $bob->id]);
    ChatMessage::factory()->create(['sender_id' => $bob->id, 'recipient_id' => $carol->id]);

    $this->actingAs($this->admin)->get("/admin/chats?user={$alice->id}")
        ->assertInertia(fn ($page) => $page
            ->where('filters.user.id', $alice->id)
            ->has('conversations.data', 1)
            ->where('conversations.data.0.user_a.id', min($alice->id, $bob->id)));
});

it('shows the full transcript between two users without marking it read', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $alice->id, 'friend_id' => $bob->id]);

    ChatMessage::factory()->create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'body' => 'salam']);
    ChatMessage::factory()->create(['sender_id' => $bob->id, 'recipient_id' => $alice->id, 'body' => 'wa alaykum']);
    ChatMessage::factory()->create(['sender_id' => $carol->id, 'recipient_id' => $alice->id, 'body' => 'other chat']);

    $this->actingAs($this->admin)->get("/admin/chats/{$bob->id}/{$alice->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Chats/Show')
            ->where('friends', true)
            ->where('total', 2)
            ->where('messages.0.body', 'salam')
            ->where('messages.1.body', 'wa alaykum')
            ->where('has_more', false));

    expect(ChatMessage::whereNotNull('read_at')->count())->toBe(0);
});

it('pages the transcript backwards', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    ChatMessage::factory()->count(105)->create(['sender_id' => $alice->id, 'recipient_id' => $bob->id]);

    $latest = $this->actingAs($this->admin)->get("/admin/chats/{$alice->id}/{$bob->id}");
    $latest->assertInertia(fn ($page) => $page->has('messages', 100)->where('has_more', true));

    $firstShown = $latest->viewData('page')['props']['messages'][0]['id'];

    $this->actingAs($this->admin)->get("/admin/chats/{$alice->id}/{$bob->id}?before={$firstShown}")
        ->assertInertia(fn ($page) => $page->has('messages', 5)->where('has_more', false));
});

it('shows a user\'s recent chats on the admin user page', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    ChatMessage::factory()->count(3)->create(['sender_id' => $alice->id, 'recipient_id' => $bob->id]);

    $this->actingAs($this->admin)->get("/admin/users/{$alice->id}")
        ->assertInertia(fn ($page) => $page
            ->where('chats.total', 1)
            ->where('chats.recent.0.other_user.id', $bob->id)
            ->where('chats.recent.0.messages_count', 3));
});
