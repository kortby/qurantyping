<?php

namespace App\Services;

use App\Models\QuranText;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuranMapService
{
    public function __construct(private readonly QuranNavigator $navigator) {}

    private function masteryBar(): float
    {
        return (float) config('hifz.certificate_accuracy', 95);
    }

    /**
     * The whole-map payload: per-surah + per-juz coverage counts, plus totals.
     *
     * @return array{
     *   surahs: list<array<string, mixed>>,
     *   juz: list<array<string, int|float>>,
     *   totals: array<string, int|float>
     * }
     */
    public function overview(User $user): array
    {
        $bar = $this->masteryBar();

        $completionsBySurah = $this->completionCounts($user->id, 'surah_number', $bar);
        $memorisedBySurah = $this->memorisedCounts($user->id, 'surah_number');
        $completionsByJuz = $this->completionCounts($user->id, 'juz', $bar);
        $memorisedByJuz = $this->memorisedCounts($user->id, 'juz');

        $surahs = [];
        $juzTotals = [];
        $grand = ['ayah_count' => 0, 'practiced' => 0, 'mastered' => 0, 'memorised' => 0];

        foreach ($this->navigator->surahIndex() as $s) {
            $n = $s['number'];
            $practiced = (int) ($completionsBySurah[$n]->practiced ?? 0);
            $mastered = (int) ($completionsBySurah[$n]->mastered ?? 0);
            $memorised = (int) ($memorisedBySurah[$n] ?? 0);

            $surahs[] = [
                'number' => $n,
                'name_en' => $s['name_en'],
                'name_ar' => $s['name_ar'],
                'juz' => $s['juz'],
                'ayah_count' => $s['ayah_count'],
                'practiced' => $practiced,
                'mastered' => $mastered,
                'memorised' => $memorised,
            ];

            $grand['ayah_count'] += $s['ayah_count'];
            $grand['practiced'] += $practiced;
            $grand['mastered'] += $mastered;
            $grand['memorised'] += $memorised;

            $j = $s['juz'];
            $juzTotals[$j] ??= ['ayah_count' => 0];
            $juzTotals[$j]['ayah_count'] += $s['ayah_count'];
        }

        $juz = [];
        for ($j = 1; $j <= 30; $j++) {
            $juz[] = [
                'juz' => $j,
                'ayah_count' => (int) ($juzTotals[$j]['ayah_count'] ?? 0),
                'practiced' => (int) ($completionsByJuz[$j]->practiced ?? 0),
                'mastered' => (int) ($completionsByJuz[$j]->mastered ?? 0),
                'memorised' => (int) ($memorisedByJuz[$j] ?? 0),
            ];
        }

        return [
            'surahs' => $surahs,
            'juz' => $juz,
            'totals' => [
                'ayah_count' => $grand['ayah_count'],
                'practiced' => $grand['practiced'],
                'mastered' => $grand['mastered'],
                'memorised' => $grand['memorised'],
                'practiced_pct' => $this->pct($grand['practiced'], $grand['ayah_count']),
                'mastered_pct' => $this->pct($grand['mastered'], $grand['ayah_count']),
                'memorised_pct' => $this->pct($grand['memorised'], $grand['ayah_count']),
            ],
        ];
    }

    /**
     * Per-ayah state for one surah.
     *
     * @return array{surah_number:int, name_en:string, name_ar:string, ayahs: list<array{n:int, state:string}>}
     */
    public function surahDetail(User $user, int $surah): array
    {
        $bar = $this->masteryBar();

        $ayahs = QuranText::where('surah_number', $surah)
            ->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'surah_name_english', 'surah_name_arabic']);

        $ids = $ayahs->pluck('id');

        $accuracy = DB::table('user_ayah_completions')
            ->where('user_id', $user->id)
            ->whereIn('quran_text_id', $ids)
            ->pluck('best_accuracy', 'quran_text_id');

        $memorised = DB::table('user_ayah_progress')
            ->where('user_id', $user->id)
            ->whereIn('quran_text_id', $ids)
            ->pluck('quran_text_id')
            ->flip();

        return [
            'surah_number' => $surah,
            'name_en' => (string) ($ayahs->first()->surah_name_english ?? ''),
            'name_ar' => (string) ($ayahs->first()->surah_name_arabic ?? ''),
            'ayahs' => $ayahs->map(function ($a) use ($accuracy, $memorised, $bar): array {
                $state = 'untouched';
                if ($memorised->has($a->id)) {
                    $state = 'memorised';
                } elseif ($accuracy->has($a->id)) {
                    $state = (float) $accuracy[$a->id] >= $bar ? 'mastered' : 'practiced';
                }

                return ['n' => (int) $a->ayah_number, 'state' => $state];
            })->all(),
        ];
    }

    /**
     * @return Collection<int, object{practiced:int, mastered:int}>
     */
    private function completionCounts(int $userId, string $groupCol, float $bar)
    {
        return DB::table('user_ayah_completions as uac')
            ->join('quran_texts as qt', 'qt.id', '=', 'uac.quran_text_id')
            ->where('uac.user_id', $userId)
            ->groupBy("qt.{$groupCol}")
            ->selectRaw("qt.{$groupCol} as k, COUNT(*) as practiced, SUM(CASE WHEN uac.best_accuracy >= ? THEN 1 ELSE 0 END) as mastered", [$bar])
            ->get()
            ->keyBy('k');
    }

    /**
     * @return Collection<int, int>
     */
    private function memorisedCounts(int $userId, string $groupCol)
    {
        return DB::table('user_ayah_progress as uap')
            ->join('quran_texts as qt', 'qt.id', '=', 'uap.quran_text_id')
            ->where('uap.user_id', $userId)
            ->groupBy("qt.{$groupCol}")
            ->selectRaw("qt.{$groupCol} as k, COUNT(*) as c")
            ->pluck('c', 'k');
    }

    private function pct(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
