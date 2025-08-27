<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestPageController extends Controller
{
    /**
     * Display the main typing test page.
     */
    public function __invoke(): Response
    {
        // This will render the Vue component located at:
        // resources/js/Pages/TypingTest.vue
        return Inertia::render('TypingTest');
    }
}
