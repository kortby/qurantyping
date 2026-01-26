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
        // Get top 20 scorers based on their personal best WPM
        $topScorers = DB::table('tests')
            ->join('users', 'tests.user_id', '=', 'users.id')
            ->select('users.name', DB::raw('MAX(tests.wpm) as best_wpm'), DB::raw('MAX(tests.accuracy) as best_accuracy'), DB::raw('COUNT(tests.id) as total_tests'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('best_wpm')
            ->limit(20)
            ->get();

        return Inertia::render('Leaderboard', [
            'topScorers' => $topScorers,
        ]);
    }
}
