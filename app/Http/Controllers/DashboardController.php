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
            ->with('quranText:id,surah_number,ayah_number,surah_name_arabic') // Eager load relations
            ->latest()
            ->paginate(10);

        return Inertia::render('Dashboard', [
            'results' => $results,
        ]);
    }
}
