<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Test;
use App\Models\User;
use App\Services\ContestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request)
    {
        View::share('meta', [
            'title' => 'Quran Typing Leaderboard — Fastest Arabic Typists | QuranTyping',
            'description' => "See the fastest and most accurate Qur'an typists. Compare words per minute and accuracy on real Quranic text, globally or among friends.",
        ]);

        $scope = $request->query('scope') === 'friends' && $request->user() ? 'friends' : 'global';

        // Subquery to rank tests per user by WPM desc
        $sub = Test::select('*', DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY wpm DESC, accuracy DESC, created_at DESC) as rn'));

        if ($scope === 'friends') {
            $friendIds = $request->user()->friendIds()->push($request->user()->id)->all();
            $sub->whereIn('user_id', $friendIds);
        }

        // Get top 20 scorers with their best test details
        $topScorers = DB::table(DB::raw("({$sub->toSql()}) as ranked_tests"))
            ->mergeBindings($sub->getQuery())
            ->where('rn', 1)
            ->join('users', 'ranked_tests.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name',
                'ranked_tests.wpm as best_wpm',
                'ranked_tests.accuracy as best_accuracy',
                'ranked_tests.total_errors',
                'ranked_tests.char_count',
                DB::raw('(ranked_tests.wpm >= '.config('contest.min_wpm').' AND ranked_tests.accuracy >= '.config('contest.min_accuracy').' AND ranked_tests.char_count >= '.config('contest.min_char_count').') as is_eligible'),
                DB::raw('(SELECT count(*) FROM tests WHERE tests.user_id = ranked_tests.user_id) as total_tests')
            )
            ->orderByDesc('best_wpm')
            ->limit(20)
            ->get();

        $userIds = $topScorers->pluck('user_id');
        $usersWithBadges = User::with('badges')->whereIn('id', $userIds)->get()->keyBy('id');

        // tier lives only in config; map it back onto the awarded badge rows.
        $tierBySlug = collect(config('badges.list'))->pluck('tier', 'slug');

        $topScorers->transform(function ($scorer) use ($usersWithBadges, $tierBySlug) {
            $badges = $usersWithBadges->has($scorer->user_id)
                ? $usersWithBadges[$scorer->user_id]->badges
                : collect();

            $scorer->badges = $badges->map(fn ($badge): array => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'tier' => $tierBySlug[$badge->slug] ?? 'bronze',
            ])->values();

            return $scorer;
        });

        $friendIds = [];
        $requestedIds = [];
        $incomingIds = [];

        if ($user = $request->user()) {
            $friendIds = $user->friendIds()->values();

            $pending = Friendship::query()
                ->where('status', 'pending')
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)->orWhere('friend_id', $user->id);
                })
                ->get(['user_id', 'friend_id']);

            $requestedIds = $pending->where('user_id', $user->id)->pluck('friend_id')->values();
            $incomingIds = $pending->where('friend_id', $user->id)->pluck('user_id')->values();
        }

        return Inertia::render('Leaderboard', [
            'topScorers' => $topScorers,
            'contest_config' => app(ContestService::class)->getConfig(),
            'scope' => $scope,
            'canFilterFriends' => (bool) $request->user(),
            'friendIds' => $friendIds,
            'requestedIds' => $requestedIds,
            'incomingIds' => $incomingIds,
        ]);
    }
}
