<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Test;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Dashboard', [
            'results' => fn() => Test::where('user_id', $request->user()->id)
                ->with([
                    'quranText' => function ($query) {
                        $query->select('id', 'surah_number', 'ayah_number', 'surah_name_arabic');
                    }
                ])
                ->latest()
                ->paginate(15),
            'bestWpm' => fn() => (int) Test::where('user_id', $request->user()->id)->max('wpm'),
            'averageWpm' => fn() => round(Test::where('user_id', $request->user()->id)->avg('wpm')),
            'chartData' => fn() => Test::where('user_id', $request->user()->id)
                ->latest()
                ->take(30)
                ->get()
                ->reverse()
                ->values(),
            'bestTest' => fn() => Test::where('user_id', $request->user()->id)
                ->with([
                    'quranText' => function ($query) {
                        $query->select('id', 'surah_number', 'ayah_number', 'surah_name_arabic');
                    }
                ])
                ->orderByDesc('wpm')
                ->orderByDesc('accuracy')
                ->first(),
        ]);
    }
}
