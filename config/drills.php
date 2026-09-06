<?php

return [
    /*
     * Minimum keystrokes recorded for a character before it can be judged weak.
     */
    'min_attempts' => (int) env('DRILLS_MIN_ATTEMPTS', 15),

    /*
     * Miss rate (0–1) at or above which a character counts as weak.
     */
    'weak_threshold' => (float) env('DRILLS_WEAK_THRESHOLD', 0.03),

    /*
     * How many weak characters a drill targets at once.
     */
    'max_chars' => (int) env('DRILLS_MAX_CHARS', 8),

    /*
     * Length, in consecutive ayahs, of a generated drill passage.
     */
    'passage_ayahs' => (int) env('DRILLS_PASSAGE_AYAHS', 3),
];
