<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\ChatMessagesRead;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ChatService
{
    /**
     * Seconds a heartbeat keeps a user marked online in the cache (Redis in production).
     */
    public const ONLINE_TTL = 75;

    /**
     * Messages returned per thread page.
     */
    public const PAGE_SIZE = 30;

    /**
     * Every accepted friend with their latest message, unread count and online flag,
     * most recent conversation first.
     *
     * @return array<int, array{id: int, name: string, profile_photo_url: string, online: bool, unread: int, last_message: ?array<string, mixed>}>
     */
    public function conversations(User $user): array
    {
        $friendIds = $user->friendIds();

        if ($friendIds->isEmpty()) {
            return [];
        }

        $lastIds = ChatMessage::query()
            ->where(function ($query) use ($user): void {
                $query->where('sender_id', $user->id)->orWhere('recipient_id', $user->id);
            })
            ->selectRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as other_id, MAX(id) as last_id', [$user->id])
            ->groupBy('other_id')
            ->pluck('last_id', 'other_id');

        $lastMessages = ChatMessage::query()
            ->whereIn('id', $lastIds->values())
            ->get()
            ->keyBy(fn (ChatMessage $message): int => $message->sender_id === $user->id ? $message->recipient_id : $message->sender_id);

        $unread = $this->unreadBySender($user);
        $online = $this->onlineIds($friendIds->all());

        return User::query()
            ->whereIn('id', $friendIds)
            ->get()
            ->map(fn (User $friend): array => [
                'id' => $friend->id,
                'name' => $friend->name,
                'profile_photo_url' => $friend->profile_photo_url,
                'online' => in_array($friend->id, $online, true),
                'unread' => (int) ($unread[$friend->id] ?? 0),
                'last_message' => $lastMessages->get($friend->id)?->toChatArray(),
            ])
            ->sortBy([
                fn (array $a, array $b): int => ($b['last_message']['id'] ?? 0) <=> ($a['last_message']['id'] ?? 0),
                fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * A page of the conversation with a friend, oldest first.
     *
     * @return array{messages: array<int, array<string, mixed>>, has_more: bool}
     */
    public function thread(User $user, User $friend, ?int $beforeId = null): array
    {
        $rows = ChatMessage::query()
            ->between($user->id, $friend->id)
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        return [
            'messages' => $rows->take(self::PAGE_SIZE)
                ->reverse()
                ->map(fn (ChatMessage $message): array => $message->toChatArray())
                ->values()
                ->all(),
            'has_more' => $rows->count() > self::PAGE_SIZE,
        ];
    }

    /**
     * Store a message and push it to both participants over the socket.
     */
    public function send(User $from, User $to, string $body): ChatMessage
    {
        $message = ChatMessage::create([
            'sender_id' => $from->id,
            'recipient_id' => $to->id,
            'body' => $body,
        ]);

        $this->markOnline($from);

        ChatMessageSent::dispatch($message);

        return $message;
    }

    /**
     * Mark everything the friend sent to the user as read, telling the friend they were seen.
     */
    public function markRead(User $user, User $friend): int
    {
        $unread = ChatMessage::query()
            ->where('sender_id', $friend->id)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at');

        $upToId = (int) (clone $unread)->max('id');

        if ($upToId === 0) {
            return 0;
        }

        $readAt = now();
        $count = $unread->where('id', '<=', $upToId)->update(['read_at' => $readAt]);

        ChatMessagesRead::dispatch($user->id, $friend->id, $upToId, $readAt->toIso8601String());

        return $count;
    }

    /**
     * Total unread messages from current friends.
     */
    public function unreadCount(User $user): int
    {
        return (int) array_sum($this->unreadBySender($user));
    }

    /**
     * Whether the user may join the private `chat.{low}.{high}` channel used for
     * typing indicators: ids must be ordered and the other party an accepted friend.
     */
    public function canJoinPairChannel(User $user, int $lowId, int $highId): bool
    {
        if ($lowId >= $highId || ! in_array($user->id, [$lowId, $highId], true)) {
            return false;
        }

        return $user->friendIds()->contains($user->id === $lowId ? $highId : $lowId);
    }

    /**
     * Record a heartbeat for the user.
     */
    public function markOnline(User $user): void
    {
        Cache::put($this->onlineKey($user->id), true, self::ONLINE_TTL);
    }

    /**
     * The subset of the given user ids with a live heartbeat.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    public function onlineIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $keys = array_map(fn (int $id): string => $this->onlineKey($id), $userIds);
        $values = Cache::many($keys);

        return array_values(array_filter(
            $userIds,
            fn (int $id): bool => (bool) ($values[$this->onlineKey($id)] ?? false),
        ));
    }

    /**
     * Unread counts keyed by sender id, limited to current friends.
     *
     * @return array<int, int>
     */
    private function unreadBySender(User $user): array
    {
        $friendIds = $user->friendIds();

        if ($friendIds->isEmpty()) {
            return [];
        }

        return ChatMessage::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->whereIn('sender_id', $friendIds)
            ->selectRaw('sender_id, COUNT(*) as unread')
            ->groupBy('sender_id')
            ->pluck('unread', 'sender_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function onlineKey(int $userId): string
    {
        return 'chat:online:'.$userId;
    }
}
