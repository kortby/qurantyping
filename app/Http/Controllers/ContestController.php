<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\ContestService;
use Inertia\Inertia;

class ContestController extends Controller
{
    public function __construct(private ContestService $contest)
    {
    }

    public function index()
    {
        if (!$this->contest->isActive()) {
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
