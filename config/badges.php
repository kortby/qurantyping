<?php

/*
 * The badge catalogue — display data only. Award rules live in
 * App\Services\BadgeService (closures can't be config:cache'd).
 *
 * icon: flame | bolt | star | book | trophy | crescent
 * tier: bronze | silver | gold
 */

return [
    'list' => [
        ['slug' => 'first-test', 'name' => 'First steps', 'description' => 'Complete your first typing test.', 'icon' => 'star', 'tier' => 'bronze'],
        ['slug' => 'tests-10', 'name' => 'Getting into it', 'description' => 'Complete 10 tests.', 'icon' => 'star', 'tier' => 'bronze'],
        ['slug' => 'tests-100', 'name' => 'Regular', 'description' => 'Complete 100 tests.', 'icon' => 'star', 'tier' => 'silver'],
        ['slug' => 'tests-1000', 'name' => 'Devoted', 'description' => 'Complete 1,000 tests.', 'icon' => 'star', 'tier' => 'gold'],

        ['slug' => 'wpm-40', 'name' => 'Warmed up', 'description' => 'Reach 40 WPM in a test.', 'icon' => 'bolt', 'tier' => 'bronze'],
        ['slug' => 'wpm-60', 'name' => 'Quick fingers', 'description' => 'Reach 60 WPM in a test.', 'icon' => 'bolt', 'tier' => 'silver'],
        ['slug' => 'wpm-80', 'name' => 'Lightning', 'description' => 'Reach 80 WPM in a test.', 'icon' => 'bolt', 'tier' => 'gold'],
        ['slug' => 'perfect-run', 'name' => 'Flawless', 'description' => 'Finish a test of 50+ characters with zero errors.', 'icon' => 'bolt', 'tier' => 'silver'],

        ['slug' => 'chars-10k', 'name' => 'Ten thousand', 'description' => 'Type 10,000 characters in total.', 'icon' => 'flame', 'tier' => 'bronze'],
        ['slug' => 'chars-100k', 'name' => 'Hundred thousand', 'description' => 'Type 100,000 characters in total.', 'icon' => 'flame', 'tier' => 'gold'],

        ['slug' => 'streak-7', 'name' => 'One week', 'description' => 'Keep a 7-day practice streak.', 'icon' => 'flame', 'tier' => 'bronze'],
        ['slug' => 'streak-30', 'name' => 'One month', 'description' => 'Keep a 30-day practice streak.', 'icon' => 'flame', 'tier' => 'silver'],
        ['slug' => 'streak-100', 'name' => 'Unbroken', 'description' => 'Keep a 100-day practice streak.', 'icon' => 'flame', 'tier' => 'gold'],

        ['slug' => 'first-surah', 'name' => 'First surah', 'description' => 'Earn your first surah certificate.', 'icon' => 'book', 'tier' => 'bronze'],
        ['slug' => 'certs-5', 'name' => 'Five surahs', 'description' => 'Earn 5 surah certificates.', 'icon' => 'book', 'tier' => 'silver'],
        ['slug' => 'certs-10', 'name' => 'Ten surahs', 'description' => 'Earn 10 surah certificates.', 'icon' => 'book', 'tier' => 'gold'],
        ['slug' => 'juz-amma', 'name' => 'Juz Amma', 'description' => 'Certify every surah of the 30th juz.', 'icon' => 'book', 'tier' => 'gold'],

        ['slug' => 'hifz-10', 'name' => 'Memorising', 'description' => 'Have 10 ayahs in hifz review.', 'icon' => 'crescent', 'tier' => 'bronze'],
        ['slug' => 'hifz-50', 'name' => 'Hafiz path', 'description' => 'Have 50 ayahs in hifz review.', 'icon' => 'crescent', 'tier' => 'silver'],

        ['slug' => 'race-win', 'name' => 'Champion', 'description' => 'Finish first in a typing race.', 'icon' => 'trophy', 'tier' => 'silver'],
        ['slug' => 'contest-entry', 'name' => 'Contender', 'description' => 'Log a qualifying contest entry.', 'icon' => 'trophy', 'tier' => 'bronze'],
    ],
];
