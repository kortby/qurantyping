<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Messages shown per transcript page.
     */
    public const TRANSCRIPT_PAGE_SIZE = 100;

    public function __construct(private ChatService $chat) {}

    /**
     * Every conversation between two users, newest first, optionally for one user.
     */
    public function index(Request $request): Response
    {
        $filterUser = $request->integer('user') ? User::find($request->integer('user'), ['id', 'name']) : null;

        $pairs = $this->chat->conversationPairs($filterUser?->id)
            ->paginate(25)
            ->withQueryString();

        $rows = collect($pairs->items());
        $users = User::query()
            ->whereIn('id', $rows->flatMap(fn ($row): array => [$row->user_a, $row->user_b])->unique())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');
        $lastMessages = ChatMessage::query()->whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return Inertia::render('Admin/Chats/Index', [
            'filters' => ['user' => $filterUser ? ['id' => $filterUser->id, 'name' => $filterUser->name] : null],
            'total' => fn (): int => ChatMessage::query()->count(),
            'conversations' => $pairs->through(fn ($row): array => [
                'user_a' => $this->person($users, (int) $row->user_a),
                'user_b' => $this->person($users, (int) $row->user_b),
                'messages_count' => (int) $row->messages_count,
                'last_message' => $lastMessages->get($row->last_id)?->toChatArray(),
            ]),
        ]);
    }

    /**
     * The read-only transcript between two users, oldest first, paged backwards.
     */
    public function show(Request $request, User $user, User $other): Response
    {
        $rows = ChatMessage::query()
            ->between($user->id, $other->id)
            ->when($request->integer('before'), fn ($query, int $before) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::TRANSCRIPT_PAGE_SIZE + 1)
            ->get();

        return Inertia::render('Admin/Chats/Show', [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'profile_photo_url' => $user->profile_photo_url],
            'other' => ['id' => $other->id, 'name' => $other->name, 'email' => $other->email, 'profile_photo_url' => $other->profile_photo_url],
            'friends' => $user->isFriendsWith($other),
            'total' => ChatMessage::query()->between($user->id, $other->id)->count(),
            'messages' => $rows->take(self::TRANSCRIPT_PAGE_SIZE)
                ->reverse()
                ->map(fn (ChatMessage $message): array => $message->toChatArray())
                ->values()
                ->all(),
            'has_more' => $rows->count() > self::TRANSCRIPT_PAGE_SIZE,
            'before' => $request->integer('before') ?: null,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{id: int, name: string, email: ?string}
     */
    private function person(Collection $users, int $id): array
    {
        $user = $users->get($id);

        return ['id' => $id, 'name' => $user?->name ?? 'Deleted user', 'email' => $user?->email];
    }
}
