<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Test;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Fetch the logged-in user's test results, newest first, with pagination
        $results = Test::where('user_id', $request->user()->id)
            ->with([
                'quranText' => function ($query) {
                    $query->select('id', 'surah_number', 'ayah_number', 'surah_name_arabic');
                }
            ])
            ->latest()
            ->paginate(15);

        // Get the best WPM for the crown icon
        $bestWpm = Test::where('user_id', $request->user()->id)->max('wpm');

        // Calculate the average WPM
        $averageWpm = Test::where('user_id', $request->user()->id)->avg('wpm');

        // Fetch chart data (last 30 tests, sorted chronologically)
        $chartData = Test::where('user_id', $request->user()->id)
            ->latest()
            ->take(30)
            ->get()
            ->reverse()
            ->values();

        return Inertia::render('Dashboard', [
            'results' => $results,
            'bestWpm' => (int) $bestWpm,
            'averageWpm' => round($averageWpm),
            'chartData' => $chartData,
        ]);
    }
}
