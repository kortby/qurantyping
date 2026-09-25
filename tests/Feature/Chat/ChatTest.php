<?php

use App\Events\ChatMessageSent;
use App\Events\ChatMessagesRead;
use App\Models\ChatMessage;
use App\Models\Friendship;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

function chatFriends(): array
{
    $me = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['user_id' => $me->id, 'friend_id' => $friend->id]);

    return [$me, $friend];
}

it('sends a message to an accepted friend and broadcasts it', function () {
    Event::fake([ChatMessageSent::class]);
    [$me, $friend] = chatFriends();

    actingAs($me)->postJson("/chat/{$friend->id}", ['body' => '  Salam!  '])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Salam!')
        ->assertJsonPath('message.recipient_id', $friend->id);

    $this->assertDatabaseHas('chat_messages', [
        'sender_id' => $me->id, 'recipient_id' => $friend->id, 'body' => 'Salam!',
    ]);
    Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event): bool => $event->message->body === 'Salam!'
        && collect($event->broadcastOn())->map->name->sort()->values()->all() === collect([
            'private-App.Models.User.'.$friend->id,
            'private-App.Models.User.'.$me->id,
        ])->sort()->values()->all());
});

it('forbids messaging someone who is not an accepted friend', function (string $status) {
    Event::fake([ChatMessageSent::class]);
    $me = User::factory()->create();
    $other = User::factory()->create();

    if ($status === 'pending') {
        Friendship::factory()->create(['user_id' => $me->id, 'friend_id' => $other->id]);
    }

    actingAs($me)->postJson("/chat/{$other->id}", ['body' => 'hi'])->assertForbidden();
    actingAs($me)->getJson("/chat/{$other->id}")->assertForbidden();
    actingAs($me)->postJson("/chat/{$other->id}/read")->assertForbidden();

    expect(ChatMessage::count())->toBe(0);
    Event::assertNotDispatched(ChatMessageSent::class);
})->with(['stranger' => 'none', 'pending request' => 'pending']);

it('rejects blank and oversized messages', function (string $body) {
    [$me, $friend] = chatFriends();

    actingAs($me)->postJson("/chat/{$friend->id}", ['body' => $body])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
})->with([
    'empty' => '',
    'whitespace' => '    ',
    'too long' => str_repeat('a', 2001),
]);

it('requires authentication', function () {
    $friend = User::factory()->create();

    $this->getJson('/chat')->assertUnauthorized();
    $this->postJson("/chat/{$friend->id}", ['body' => 'hi'])->assertUnauthorized();
});

it('returns a thread oldest first and pages backwards', function () {
    [$me, $friend] = chatFriends();
    $stranger = User::factory()->create();
    ChatMessage::factory()->create(['sender_id' => $stranger->id, 'recipient_id' => $me->id]);

    foreach (range(1, ChatService::PAGE_SIZE + 5) as $i) {
        ChatMessage::factory()->create([
            'sender_id' => $i % 2 ? $me->id : $friend->id,
            'recipient_id' => $i % 2 ? $friend->id : $me->id,
            'body' => "m{$i}",
        ]);
    }

    $page = actingAs($me)->getJson("/chat/{$friend->id}")->assertOk()->json();

    expect($page['has_more'])->toBeTrue()
        ->and($page['messages'])->toHaveCount(ChatService::PAGE_SIZE)
        ->and($page['messages'][0]['body'])->toBe('m6')
        ->and(end($page['messages'])['body'])->toBe('m'.(ChatService::PAGE_SIZE + 5));

    $older = actingAs($me)->getJson("/chat/{$friend->id}?before={$page['messages'][0]['id']}")->json();

    expect($older['has_more'])->toBeFalse()
        ->and(collect($older['messages'])->pluck('body')->all())->toBe(['m1', 'm2', 'm3', 'm4', 'm5']);
});

it('lists friends with last message, unread count and presence, newest first', function () {
    [$me, $alice] = chatFriends();
    $bob = User::factory()->create(['name' => 'Bob']);
    Friendship::factory()->accepted()->create(['user_id' => $bob->id, 'friend_id' => $me->id]);
    $stranger = User::factory()->create();

    ChatMessage::factory()->create(['sender_id' => $alice->id, 'recipient_id' => $me->id, 'body' => 'old']);
    ChatMessage::factory()->count(2)->create(['sender_id' => $bob->id, 'recipient_id' => $me->id]);
    ChatMessage::factory()->create(['sender_id' => $me->id, 'recipient_id' => $bob->id, 'body' => 'latest']);
    ChatMessage::factory()->create(['sender_id' => $stranger->id, 'recipient_id' => $me->id]);

    app(ChatService::class)->markOnline($bob);

    $list = actingAs($me)->getJson('/chat')->assertOk()->json('conversations');

    expect(collect($list)->pluck('id')->all())->toBe([$bob->id, $alice->id])
        ->and($list[0]['last_message']['body'])->toBe('latest')
        ->and($list[0]['unread'])->toBe(2)
        ->and($list[0]['online'])->toBeTrue()
        ->and($list[1]['unread'])->toBe(1)
        ->and($list[1]['online'])->toBeFalse()
        ->and(app(ChatService::class)->unreadCount($me))->toBe(3);
});

it('marks the viewer online and reports online friends on heartbeat', function () {
    [$me, $friend] = chatFriends();
    $stranger = User::factory()->create();
    app(ChatService::class)->markOnline($friend);
    app(ChatService::class)->markOnline($stranger);

    actingAs($me)->postJson('/chat/heartbeat')
        ->assertOk()
        ->assertExactJson(['online' => [$friend->id]]);

    expect(app(ChatService::class)->onlineIds([$me->id]))->toBe([$me->id]);
});

it('marks a friend\'s messages read and notifies the sender', function () {
    Event::fake([ChatMessagesRead::class]);
    [$me, $friend] = chatFriends();
    $incoming = ChatMessage::factory()->count(2)->create(['sender_id' => $friend->id, 'recipient_id' => $me->id]);
    $mine = ChatMessage::factory()->create(['sender_id' => $me->id, 'recipient_id' => $friend->id]);

    actingAs($me)->postJson("/chat/{$friend->id}/read")->assertOk()->assertJson(['read' => 2]);

    expect($incoming->fresh()->every(fn (ChatMessage $message): bool => $message->read_at !== null))->toBeTrue()
        ->and($mine->fresh()->read_at)->toBeNull();
    Event::assertDispatched(ChatMessagesRead::class, fn (ChatMessagesRead $event): bool => $event->senderId === $friend->id
        && $event->upToId === $incoming->last()->id);

    actingAs($me)->postJson("/chat/{$friend->id}/read")->assertJson(['read' => 0]);
    Event::assertDispatchedTimes(ChatMessagesRead::class, 1);
});

it('shares the unread chat count with every page', function () {
    [$me, $friend] = chatFriends();
    ChatMessage::factory()->count(3)->create(['sender_id' => $friend->id, 'recipient_id' => $me->id]);

    actingAs($me)->get('/friends')
        ->assertInertia(fn ($page) => $page->where('auth.chat_unread', 3));
});

it('lets only the two friends join their typing channel', function () {
    [$me, $friend] = chatFriends();
    $stranger = User::factory()->create();
    Friendship::factory()->create(['user_id' => $stranger->id, 'friend_id' => $me->id]);
    [$low, $high] = collect([$me->id, $friend->id])->sort()->values()->all();
    $chat = app(ChatService::class);

    expect($chat->canJoinPairChannel($me, $low, $high))->toBeTrue()
        ->and($chat->canJoinPairChannel($friend, $low, $high))->toBeTrue()
        ->and($chat->canJoinPairChannel($stranger, $low, $high))->toBeFalse()
        ->and($chat->canJoinPairChannel($me, $high, $low))->toBeFalse()
        ->and($chat->canJoinPairChannel($me, min($me->id, $stranger->id), max($me->id, $stranger->id)))->toBeFalse();
});
