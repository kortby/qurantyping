<?php

namespace App\Services;

use App\Models\QuranText;
use App\Models\UserLetterStat;
use Illuminate\Support\Collection;

class WeakLetterService
{
    /**
     * Fold a completed test's per-character tallies into the user's aggregate.
     *
     * @param  iterable<int, array{c?: string, attempts?: int|string, misses?: int|string}>  $rows
     */
    public function record(?int $userId, iterable $rows): void
    {
        if (! $userId) {
            return;
        }

        foreach ($rows as $row) {
            $char = (string) ($row['c'] ?? '');

            if ($char === '') {
                continue;
            }

            $attempts = max(0, (int) ($row['attempts'] ?? 0));

            if ($attempts === 0) {
                continue;
            }

            $misses = min($attempts, max(0, (int) ($row['misses'] ?? 0)));

            $stat = UserLetterStat::firstOrNew([
                'user_id' => $userId,
                'character' => $char,
            ]);

            $stat->attempts = (int) $stat->attempts + $attempts;
            $stat->misses = (int) $stat->misses + $misses;
            $stat->save();
        }
    }

    /**
     * The user's weakest characters, worst first, filtered by confidence and severity.
     *
     * @return Collection<int, array{character: string, attempts: int, misses: int, miss_rate: float}>
     */
    public function weakChars(?int $userId): Collection
    {
        if (! $userId) {
            return collect();
        }

        return UserLetterStat::query()
            ->where('user_id', $userId)
            ->where('attempts', '>=', (int) config('drills.min_attempts'))
            ->get()
            ->map(fn (UserLetterStat $stat): array => [
                'character' => $stat->character,
                'attempts' => (int) $stat->attempts,
                'misses' => (int) $stat->misses,
                'miss_rate' => (int) $stat->misses / (int) $stat->attempts,
            ])
            ->filter(fn (array $row): bool => $row['miss_rate'] >= (float) config('drills.weak_threshold'))
            ->sortByDesc('miss_rate')
            ->take((int) config('drills.max_chars'))
            ->values();
    }

    /**
     * Pick the consecutive-ayah window densest in the given weak characters.
     *
     * @param  Collection<int, array{character: string, miss_rate: float}>  $weak
     * @return array{surah_number: int, start_ayah: int, end_ayah: int}|null
     */
    public function drillPassage(Collection $weak): ?array
    {
        if ($weak->isEmpty()) {
            return null;
        }

        $weights = $weak->mapWithKeys(fn (array $row): array => [$row['character'] => $row['miss_rate']]);
        $window = max(1, (int) config('drills.passage_ayahs'));

        $ayahs = QuranText::query()
            ->orderBy('surah_number')
            ->orderBy('ayah_number')
            ->get(['surah_number', 'ayah_number', 'text_arabic_simple', 'surah_arabic_ponctuation']);

        $best = null;
        $bestScore = -1.0;

        foreach ($ayahs->groupBy('surah_number') as $surahNumber => $surahAyahs) {
            $rows = $surahAyahs->values();
            $scores = $rows->map(function ($ayah) use ($weights): float {
                $text = $ayah->surah_arabic_ponctuation ?: $ayah->text_arabic_simple;
                $score = 0.0;

                foreach ($weights as $char => $weight) {
                    $score += substr_count($text, $char) * $weight;
                }

                return $score;
            });

            $limit = max(1, $rows->count() - $window + 1);

            for ($i = 0; $i < $limit; $i++) {
                $slice = $scores->slice($i, $window);
                $windowScore = $slice->sum();

                if ($windowScore > $bestScore) {
                    $bestScore = $windowScore;
                    $best = [
                        'surah_number' => (int) $surahNumber,
                        'start_ayah' => (int) $rows[$i]->ayah_number,
                        'end_ayah' => (int) $rows[min($i + $window - 1, $rows->count() - 1)]->ayah_number,
                    ];
                }
            }
        }

        return $best;
    }
}
