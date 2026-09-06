<?php

namespace App\Services;

use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class QuranNavigator
{
    /** Ayahs loaded at a division start or after a resume. */
    private const WINDOW = 5;

    /**
     * Resolve a scope + value to a starting passage location.
     *
     * @return array{surah_number:int, start_ayah:int, end_ayah:int}|null
     */
    public function resolve(string $scope, int $value): ?array
    {
        $column = match ($scope) {
            'juz' => 'juz',
            'page' => 'page',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $start = QuranText::where($column, $value)
            ->orderBy('surah_number')
            ->orderBy('ayah_number')
            ->first(['surah_number', 'ayah_number']);

        return $start ? $this->window($start->surah_number, $start->ayah_number) : null;
    }

    /**
     * The passage that begins with the ayah immediately after the given one.
     * Returns null at the end of the Quran.
     *
     * @return array{surah_number:int, start_ayah:int, end_ayah:int}|null
     */
    public function nextAfter(int $quranTextId): ?array
    {
        $ref = QuranText::find($quranTextId, ['surah_number', 'ayah_number']);

        if (! $ref) {
            return null;
        }

        $maxInSurah = (int) QuranText::where('surah_number', $ref->surah_number)->max('ayah_number');

        if ($ref->ayah_number < $maxInSurah) {
            return $this->window($ref->surah_number, $ref->ayah_number + 1);
        }

        $nextSurahExists = QuranText::where('surah_number', $ref->surah_number + 1)
            ->where('ayah_number', 1)
            ->exists();

        return $nextSurahExists ? $this->window($ref->surah_number + 1, 1) : null;
    }

    /**
     * Remember where a completed test finished, for the "Continue" affordance.
     */
    public function rememberProgress(Test $test): void
    {
        if (! $test->user_id || ! $test->end_ayah) {
            return;
        }

        $surah = QuranText::whereKey($test->quran_text_id)->value('surah_number');

        if (! $surah) {
            return;
        }

        $lastId = QuranText::where('surah_number', $surah)
            ->where('ayah_number', $test->end_ayah)
            ->value('id');

        if ($lastId) {
            User::whereKey($test->user_id)->update(['last_quran_text_id' => $lastId]);
        }
    }

    /**
     * The "Continue" payload for a user, or null when there's nothing to resume.
     *
     * @return array{after:int, label:string}|null
     */
    public function resumePoint(User $user): ?array
    {
        if (! $user->last_quran_text_id) {
            return null;
        }

        $next = $this->nextAfter($user->last_quran_text_id);

        if (! $next) {
            return null;
        }

        $name = QuranText::where('surah_number', $next['surah_number'])->value('surah_name_english');

        return [
            'after' => (int) $user->last_quran_text_id,
            'label' => trim("{$name} {$next['start_ayah']}"),
        ];
    }

    /**
     * The 30 juz' with the surah each one opens on. Cached indefinitely.
     *
     * @return list<array{value:int, surah:string, ayah:int}>
     */
    public function juzIndex(): array
    {
        return Cache::rememberForever('quran.juz_index', function (): array {
            return collect(range(1, 30))->map(function (int $juz): array {
                $first = QuranText::where('juz', $juz)
                    ->orderBy('surah_number')
                    ->orderBy('ayah_number')
                    ->first(['ayah_number', 'surah_name_english']);

                return [
                    'value' => $juz,
                    'surah' => (string) ($first->surah_name_english ?? ''),
                    'ayah' => (int) ($first->ayah_number ?? 1),
                ];
            })->all();
        });
    }

    public function pageCount(): int
    {
        return Cache::rememberForever('quran.page_count', fn (): int => (int) QuranText::max('page'));
    }

    /**
     * Every surah with its names, ayah count, and the span of juz it covers
     * (`juz` = first ayah's juz, `juz_end` = last ayah's juz — long surahs span several).
     *
     * @return list<array{number:int, name_en:string, name_ar:string, ayah_count:int, juz:int, juz_end:int}>
     */
    public function surahIndex(): array
    {
        return Cache::rememberForever('quran.surah_index', function (): array {
            return QuranText::query()
                ->selectRaw('surah_number, MAX(surah_name_english) as name_en, MAX(surah_name_arabic) as name_ar, COUNT(*) as ayah_count, MIN(juz) as juz, MAX(juz) as juz_end')
                ->groupBy('surah_number')
                ->orderBy('surah_number')
                ->get()
                ->map(fn ($row): array => [
                    'number' => (int) $row->surah_number,
                    'name_en' => (string) $row->name_en,
                    'name_ar' => (string) $row->name_ar,
                    'ayah_count' => (int) $row->ayah_count,
                    'juz' => (int) $row->juz,
                    'juz_end' => (int) $row->juz_end,
                ])
                ->all();
        });
    }

    /**
     * Ayah count per juz, straight from the per-ayah `juz` column.
     *
     * @return array<int, int>
     */
    public function juzAyahCounts(): array
    {
        return Cache::rememberForever('quran.juz_ayah_counts', function (): array {
            return QuranText::query()
                ->selectRaw('juz, COUNT(*) as c')
                ->groupBy('juz')
                ->pluck('c', 'juz')
                ->map(fn ($c): int => (int) $c)
                ->all();
        });
    }

    /**
     * A passage that opens at the given ayah, running up to WINDOW ayahs but
     * never past the end of its surah.
     *
     * @return array{surah_number:int, start_ayah:int, end_ayah:int}
     */
    public function window(int $surah, int $startAyah): array
    {
        $maxAyah = (int) QuranText::where('surah_number', $surah)->max('ayah_number');

        return [
            'surah_number' => $surah,
            'start_ayah' => $startAyah,
            'end_ayah' => min($startAyah + self::WINDOW - 1, $maxAyah),
        ];
    }
}
