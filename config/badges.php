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

        // --- Stretch the ladders ---
        ['slug' => 'tests-250', 'name' => 'Dedicated', 'description' => 'Complete 250 tests.', 'icon' => 'star', 'tier' => 'silver'],
        ['slug' => 'tests-500', 'name' => 'Relentless', 'description' => 'Complete 500 tests.', 'icon' => 'star', 'tier' => 'silver'],
        ['slug' => 'wpm-100', 'name' => 'Blazing', 'description' => 'Reach 100 WPM in a test.', 'icon' => 'bolt', 'tier' => 'gold'],
        ['slug' => 'streak-14', 'name' => 'Fortnight', 'description' => 'Keep a 14-day practice streak.', 'icon' => 'flame', 'tier' => 'bronze'],
        ['slug' => 'streak-365', 'name' => 'A full year', 'description' => 'Keep a 365-day practice streak.', 'icon' => 'flame', 'tier' => 'gold'],
        ['slug' => 'chars-500k', 'name' => 'Half a million', 'description' => 'Type 500,000 characters in total.', 'icon' => 'flame', 'tier' => 'silver'],
        ['slug' => 'certs-25', 'name' => 'Twenty-five surahs', 'description' => 'Earn 25 surah certificates.', 'icon' => 'book', 'tier' => 'gold'],
        ['slug' => 'hifz-100', 'name' => 'Hundred ayahs', 'description' => 'Have 100 ayahs in hifz review.', 'icon' => 'crescent', 'tier' => 'silver'],
        ['slug' => 'hifz-300', 'name' => 'Three hundred ayahs', 'description' => 'Have 300 ayahs in hifz review.', 'icon' => 'crescent', 'tier' => 'gold'],

        // --- Consistency ---
        ['slug' => 'days-30', 'name' => 'A month of practice', 'description' => 'Practise on 30 different days.', 'icon' => 'star', 'tier' => 'bronze'],
        ['slug' => 'days-100', 'name' => 'A hundred days', 'description' => 'Practise on 100 different days.', 'icon' => 'star', 'tier' => 'silver'],
        ['slug' => 'goal-7', 'name' => 'On target', 'description' => 'Hit your daily character goal on 7 days.', 'icon' => 'flame', 'tier' => 'bronze'],
        ['slug' => 'goal-30', 'name' => 'Habit formed', 'description' => 'Hit your daily character goal on 30 days.', 'icon' => 'flame', 'tier' => 'silver'],
        ['slug' => 'perfectionist', 'name' => 'Perfectionist', 'description' => 'Finish 10 tests of 50+ characters with zero errors.', 'icon' => 'bolt', 'tier' => 'silver'],

        // --- Variety ---
        ['slug' => 'first-hifz', 'name' => 'By heart', 'description' => 'Complete a hifz memorisation session.', 'icon' => 'crescent', 'tier' => 'bronze'],
        ['slug' => 'marathon', 'name' => 'Marathon', 'description' => 'Finish a single test of 500+ characters.', 'icon' => 'bolt', 'tier' => 'bronze'],
        ['slug' => 'juz-5', 'name' => 'Five juz', 'description' => 'Certify surahs spanning 5 juz.', 'icon' => 'book', 'tier' => 'bronze'],
        ['slug' => 'juz-15', 'name' => "Half the Qur'an", 'description' => 'Certify surahs spanning 15 juz.', 'icon' => 'book', 'tier' => 'silver'],
        ['slug' => 'juz-30', 'name' => "The whole Qur'an", 'description' => 'Certify surahs spanning all 30 juz.', 'icon' => 'book', 'tier' => 'gold'],

        // --- Races ---
        ['slug' => 'podium', 'name' => 'On the podium', 'description' => 'Finish in the top 3 of a race.', 'icon' => 'trophy', 'tier' => 'bronze'],
        ['slug' => 'race-10', 'name' => 'Racer', 'description' => 'Finish 10 races.', 'icon' => 'trophy', 'tier' => 'silver'],
        ['slug' => 'rivals-5', 'name' => 'Well matched', 'description' => 'Race against 5 different people.', 'icon' => 'trophy', 'tier' => 'silver'],
    ],
];
