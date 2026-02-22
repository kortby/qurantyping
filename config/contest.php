<?php

return [
    'enabled' => env('CONTEST_ENABLED', false),
    // UTC base date for the start of Ramadan
    'ramadan_start_date' => env('RAMADAN_START_DATE', '2025-03-01T00:00:00Z'),

    // Day numbers (for reference)
    'start_day' => 26,
    'end_day' => 28,

    // Min requirements
    'min_wpm' => env('CONTEST_MIN_WPM', 150),
    'min_accuracy' => env('CONTEST_MIN_ACCURACY', 98),
    'min_char_count' => env('CONTEST_MIN_CHAR_COUNT', 100),
    'year' => env('CONTEST_YEAR', 2025),
];
