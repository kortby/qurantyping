<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

class ArabicTypingTestPageController extends Controller
{
    /**
     * Display the Arabic typing test landing page, targeted at the
     * typing-speed audience (as opposed to the hifz/memorisation audience).
     */
    public function __invoke(): Response
    {
        View::share('meta', [
            'title' => 'Free Arabic Typing Test — Practice Typing Speed & Accuracy | QuranTyping',
            'description' => 'Take a free Arabic typing test with live WPM and accuracy feedback, full harakat, and a built-in on-screen Arabic keyboard. No signup required.',
        ]);

        View::share('faq', [
            [
                'q' => 'Is this Arabic typing test free?',
                'a' => 'Yes. It is completely free and you can start typing immediately without creating an account.',
            ],
            [
                'q' => 'Do I need an Arabic keyboard to take the test?',
                'a' => 'No. An on-screen Arabic keyboard is built in, and a physical Arabic keyboard layout works too if you have one.',
            ],
            [
                'q' => 'How is my typing speed measured?',
                'a' => 'The test tracks your words per minute (WPM) and accuracy in real time as you type, and shows a full breakdown when you finish.',
            ],
            [
                'q' => 'Does the test include harakat (diacritics)?',
                'a' => 'Yes. You can toggle full harakat on to practise typing fully-vowelled Arabic text exactly as it appears in the mushaf.',
            ],
        ]);

        return Inertia::render('Marketing/ArabicTypingTest');
    }
}
