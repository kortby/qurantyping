<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DailyChallengeService
{
    public function __construct(private readonly QuranNavigator $navigator) {}

    /**
     * Today's challenge, in the application timezone.
     *
     * @return array{date:string, surah_number:int, surah_name_arabic:string, start_ayah:int, end_ayah:int}
     */
    public function today(): array
    {
        return $this->forDate(Carbon::now());
    }

    /**
     * The passage for a given date — derived purely from the date, so every
     * client computes the same one without any stored state.
     *
     * @return array{date:string, surah_number:int, surah_name_arabic:string, start_ayah:int, end_ayah:int}
     */
    public function forDate(CarbonInterface $date): array
    {
        $key = $date->toDateString();

        $eligible = array_values(array_filter(
            $this->navigator->surahIndex(),
            fn (array $surah): bool => $surah['ayah_count'] >= (int) config('daily.min_surah_ayahs', 8),
        ));

        $seed = crc32('quran-daily:'.$key);
        $count = count($eligible);

        $surah = $eligible[$seed % $count];

        $span = (int) config('daily.passage_ayahs', 4);
        $maxStart = max(1, $surah['ayah_count'] - $span + 1);
        $start = intdiv($seed, $count) % $maxStart + 1;
        $end = min($surah['ayah_count'], $start + $span - 1);

        return [
            'date' => $key,
            'surah_number' => $surah['number'],
            'surah_name_arabic' => $surah['name_ar'],
            'start_ayah' => $start,
            'end_ayah' => $end,
        ];
    }
}
