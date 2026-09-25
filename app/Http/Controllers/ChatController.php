<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /**
     * The chat dock's friend list with last message, unread counts and presence.
     */
    public function index(Request $request): JsonResponse
    {
        $this->chat->markOnline($request->user());

        return response()->json([
            'conversations' => $this->chat->conversations($request->user()),
        ]);
    }

    /**
     * Keep the user marked online and report which friends are online.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->chat->markOnline($user);

        return response()->json([
            'online' => $this->chat->onlineIds($user->friendIds()->all()),
        ]);
    }

    /**
     * A page of the conversation with a friend.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isFriendsWith($user), 403);

        return response()->json($this->chat->thread(
            $request->user(),
            $user,
            $request->integer('before') ?: null,
        ));
    }

    /**
     * Send a message to a friend.
     */
    public function store(SendChatMessageRequest $request, User $user): JsonResponse
    {
        $message = $this->chat->send($request->user(), $user, $request->validated('body'));

        return response()->json(['message' => $message->toChatArray()], 201);
    }

    /**
     * Mark the friend's messages to the user as read.
     */
    public function read(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isFriendsWith($user), 403);

        return response()->json(['read' => $this->chat->markRead($request->user(), $user)]);
    }
}
