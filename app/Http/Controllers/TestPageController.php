<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class TestPageController extends Controller
{
    /**
     * Display the main typing test page.
     */
    public function __invoke(): Response
    {
        $bestWpm = 0;
        if (auth()->check()) {
            $bestWpm = \App\Models\Test::where('user_id', auth()->id())->max('wpm') ?? 0;
        }

        return Inertia::render('TypingTest', [
            'personalBestWpm' => (int) $bestWpm,
        ]);
    }
}
