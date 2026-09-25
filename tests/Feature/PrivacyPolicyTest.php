<?php

it('discloses admin access to private messages in every language', function (string $locale, string $heading, string $adminAccess) {
    $this->withHeader('X-Locale', $locale)
        ->get('/privacy-policy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PrivacyPolicy')
            ->where('policy', fn (string $html): bool => str_contains($html, $heading) && str_contains($html, $adminAccess)));
})->with([
    'english' => ['en', 'Private messages', 'administrators can read the messages exchanged between users'],
    'french' => ['fr', 'Messages privés', 'administrateurs de QuranTyping peuvent lire les messages'],
    'arabic' => ['ar', 'الرسائل الخاصة', 'يمكن لمشرفي QuranTyping قراءة الرسائل'],
]);
