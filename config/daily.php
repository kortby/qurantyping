<?php

/*
 * The daily challenge — one Qur'an passage everyone gets per calendar day.
 * The passage itself is derived deterministically from the date (no table);
 * only completions are stored.
 */

return [
    // Only draw from surahs with at least this many ayahs, so the passage
    // comfortably clears the 10-word minimum.
    'min_surah_ayahs' => 8,

    // How many consecutive ayahs the daily passage spans.
    'passage_ayahs' => 4,
];
