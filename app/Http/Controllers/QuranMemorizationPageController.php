<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

class QuranMemorizationPageController extends Controller
{
    /**
     * Display the Quran memorisation (hifz) landing page, targeted at the
     * memorisation audience (as opposed to the typing-speed audience).
     */
    public function __invoke(): Response
    {
        View::share('meta', [
            'title' => 'Free Quran Memorization Tool — Hifz Practice with Spaced Repetition | QuranTyping',
            'description' => 'Strengthen your Quran memorisation with Hifz mode: progressively hidden text, spaced-repetition review scheduling, and typing practice on real Quranic ayat. Free.',
        ]);

        View::share('faq', [
            [
                'q' => 'How does Hifz mode help with Quran memorisation?',
                'a' => 'Hifz mode progressively hides the ayah text as you type it correctly, and schedules spaced-repetition reviews so you keep recalling what you have memorised.',
            ],
            [
                'q' => 'Do I need an account to use Hifz mode?',
                'a' => 'Yes, a free account is needed so your memorisation progress and review schedule are saved between sessions.',
            ],
            [
                'q' => 'Is this a replacement for a Quran teacher?',
                'a' => 'No. It is a practice aid that reinforces memorisation between sessions with a teacher or on your own; it does not correct your recitation or tajwid.',
            ],
            [
                'q' => 'Is Quran memorisation practice free?',
                'a' => 'Yes, Hifz mode and every other feature on QuranTyping are free to use.',
            ],
        ]);

        return Inertia::render('Marketing/QuranMemorization');
    }
}
