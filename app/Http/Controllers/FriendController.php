<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Test;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    /**
     * The friends list, pending requests both ways, and an optional name search.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $friendIds = $user->friendIds();

        $friendshipIdByUser = Friendship::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->get(['id', 'user_id', 'friend_id'])
            ->mapWithKeys(fn (Friendship $row): array => [
                ($row->user_id === $user->id ? $row->friend_id : $row->user_id) => $row->id,
            ]);

        $friends = User::whereIn('id', $friendIds)
            ->orderBy('name')
            ->get(['id', 'name', 'last_login_at']);

        $bestWpm = Test::whereIn('user_id', $friendIds)
            ->selectRaw('user_id, MAX(wpm) as wpm')
            ->groupBy('user_id')
            ->pluck('wpm', 'user_id');

        $ghostTestIds = Test::whereIn('user_id', $friendIds)
            ->whereHas('result')
            ->orderByDesc('wpm')
            ->orderByDesc('accuracy')
            ->get(['id', 'user_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->first()->id);

        return Inertia::render('Friends/Index', [
            'friends' => $friends->map(fn (User $friend): array => [
                'id' => $friend->id,
                'friendship_id' => $friendshipIdByUser[$friend->id] ?? null,
                'name' => $friend->name,
                'profile_photo_url' => $friend->profile_photo_url,
                'best_wpm' => (int) ($bestWpm[$friend->id] ?? 0),
                'last_active' => $friend->last_login_at?->toIso8601String(),
                'ghost_test_id' => $ghostTestIds[$friend->id] ?? null,
            ])->all(),
            'incoming' => $user->incomingFriendRequests()
                ->with('requester:id,name')
                ->latest()
                ->get()
                ->map(fn (Friendship $row): array => [
                    'friendship_id' => $row->id,
                    'id' => $row->requester->id,
                    'name' => $row->requester->name,
                    'profile_photo_url' => $row->requester->profile_photo_url,
                    'created_at' => $row->created_at->toIso8601String(),
                ])->all(),
            'outgoing' => $user->outgoingFriendRequests()
                ->with('recipient:id,name')
                ->latest()
                ->get()
                ->map(fn (Friendship $row): array => [
                    'friendship_id' => $row->id,
                    'id' => $row->recipient->id,
                    'name' => $row->recipient->name,
                    'profile_photo_url' => $row->recipient->profile_photo_url,
                    'created_at' => $row->created_at->toIso8601String(),
                ])->all(),
            'results' => Inertia::optional(fn (): array => $this->search($request, $user, $friendIds)),
            'query' => $request->string('q')->toString(),
            'invite_url' => url('/i/'.$user->inviteToken()),
        ]);
    }

    /**
     * Send a friend request, or accept one already pending from the target.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'friend_id' => 'required|integer|exists:users,id',
        ]);

        return back()->with(...$this->flashFor(
            Friendship::request($request->user(), (int) $validated['friend_id']),
        ));
    }

    /**
     * Land on someone's personal invite link — befriend them, handing guests
     * through registration first.
     */
    public function invite(Request $request, string $token): RedirectResponse
    {
        $owner = User::where('invite_token', $token)->first();

        abort_if(! $owner, 404);

        if (! $request->user()) {
            $request->session()->put('pending_invite', $token);

            return redirect()->route('register');
        }

        if ($owner->id === $request->user()->id) {
            return redirect()->route('friends.index');
        }

        return redirect()->route('friends.index')->with(...$this->flashFor(
            Friendship::request($request->user(), $owner->id),
        ));
    }

    /**
     * Map a Friendship::request() outcome to a flash key/message pair.
     *
     * @return array{0: string, 1: string}
     */
    private function flashFor(string $outcome): array
    {
        return match ($outcome) {
            'self' => ['error', __('You cannot add yourself.')],
            'friends' => ['message', __('You are already friends.')],
            'exists' => ['message', __('Friend request already sent.')],
            'accepted' => ['message', __('Friend request accepted.')],
            default => ['message', __('Friend request sent.')],
        };
    }

    /**
     * Accept a pending request addressed to the current user.
     */
    public function update(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless(
            $friendship->friend_id === $request->user()->id && $friendship->status === 'pending',
            403,
        );

        $friendship->update(['status' => 'accepted', 'accepted_at' => now()]);

        return back()->with('message', __('Friend request accepted.'));
    }

    /**
     * Decline, cancel, or unfriend — one destructive action for either party.
     */
    public function destroy(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless(
            in_array($request->user()->id, [$friendship->user_id, $friendship->friend_id], true),
            403,
        );

        $friendship->delete();

        return back()->with('message', __('Friend removed.'));
    }

    /**
     * Name search for people who are not yet a friend or pending with the user.
     *
     * @param  Collection<int, int>  $friendIds
     * @return array<int, array<string, mixed>>
     */
    private function search(Request $request, User $user, $friendIds): array
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return [];
        }

        $pendingIds = Friendship::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->get(['user_id', 'friend_id'])
            ->flatMap(fn (Friendship $row): array => [$row->user_id, $row->friend_id])
            ->unique();

        return User::query()
            ->where('name', 'like', '%'.$term.'%')
            ->whereNotIn('id', collect($friendIds)->push($user->id)->merge($pendingIds)->unique())
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn (User $result): array => [
                'id' => $result->id,
                'name' => $result->name,
                'profile_photo_url' => $result->profile_photo_url,
            ])->all();
    }
}
