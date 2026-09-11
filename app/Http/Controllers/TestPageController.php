<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\ContestService;
use Illuminate\Support\Facades\View;
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
            $bestWpm = Test::where('user_id', auth()->id())->max('wpm') ?? 0;
        }

        $contestService = app(ContestService::class);

        View::share('meta', [
            'title' => "Type the Qur'an Online — Free Arabic Typing Practice & Hifz | QuranTyping",
            'description' => "Practise typing the Qur'an in Arabic with live accuracy feedback, full harakat, an on-screen Arabic keyboard, and a spaced-repetition Hifz mode. Completely free.",
        ]);

        View::share('faq', [
            [
                'q' => 'What is QuranTyping?',
                'a' => "QuranTyping is a free web app for practising typing the Qur'an in Arabic. It gives real-time accuracy feedback on real Quranic text and doubles as a memorisation aid.",
            ],
            [
                'q' => 'Is QuranTyping free?',
                'a' => 'Yes. Every feature is free to use, with no account required to start typing.',
            ],
            [
                'q' => 'Can it help me memorise the Qur\'an?',
                'a' => 'Yes. Hifz mode hides the text progressively and schedules reviews with spaced repetition, so you type each passage from memory over time.',
            ],
            [
                'q' => 'Do I need an Arabic keyboard?',
                'a' => 'No. An on-screen Arabic keyboard is built in, and your physical keyboard works too if it has an Arabic layout.',
            ],
            [
                'q' => 'Does it show harakat (diacritics)?',
                'a' => 'Yes. Turn on the "Harakat" toggle to type fully-vowelled text exactly as it appears in the mushaf, which helps with correct recitation.',
            ],
        ]);

        return Inertia::render('TypingTest', [
            'personalBestWpm' => (int) $bestWpm,
            'contestConfig' => $contestService->getConfig(),
        ]);
    }
}
