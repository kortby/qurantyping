<?php

namespace App\Http\Controllers;

use App\Models\Test;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Subquery to rank tests per user by WPM desc
        $sub = Test::select('*', DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY wpm DESC, accuracy DESC, created_at DESC) as rn'));

        // Get top 20 scorers with their best test details
        $topScorers = DB::table(DB::raw("({$sub->toSql()}) as ranked_tests"))
            ->mergeBindings($sub->getQuery())
            ->where('rn', 1)
            ->join('users', 'ranked_tests.user_id', '=', 'users.id')
            ->select(
                'users.name',
                'ranked_tests.wpm as best_wpm',
                'ranked_tests.accuracy as best_accuracy',
                'ranked_tests.total_errors',
                'ranked_tests.char_count',
                DB::raw('(SELECT count(*) FROM tests WHERE tests.user_id = ranked_tests.user_id) as total_tests')
            )
            ->orderByDesc('best_wpm')
            ->limit(20)
            ->get();

        return Inertia::render('Leaderboard', [
            'topScorers' => $topScorers,
        ]);
    }
}
