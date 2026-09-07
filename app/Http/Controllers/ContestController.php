<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\ContestService;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;

class ContestController extends Controller
{
    public function __construct(private ContestService $contest) {}

    public function index()
    {
        View::share('meta', [
            'title' => 'Quran Typing Contest | QuranTyping',
            'description' => 'Compete for the top spot in the QuranTyping speed-and-accuracy contest on real Quranic passages.',
        ]);

        if (! $this->contest->isActive()) {
            return Inertia::render('Contest/Inactive', [
                'config' => $this->contest->getConfig(),
            ]);
        }

        $leaderboard = Test::contestEntries()
            ->with('user:id,name') // Only select needed user fields
            ->orderByDesc('wpm')
            ->orderByDesc('accuracy')
            ->take(50)
            ->get();

        return Inertia::render('Contest/Index', [
            'leaderboard' => $leaderboard,
            'config' => $this->contest->getConfig(),
        ]);
    }
}
