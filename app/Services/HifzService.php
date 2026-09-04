<?php

namespace App\Services;

use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class HifzService
{
    public const GRADE_AGAIN = 0;

    public const GRADE_HARD = 1;

    public const GRADE_GOOD = 2;

    public const GRADE_EASY = 3;

    private const MAX_REVIEWS_PER_DAY = 20;

    /** An ayah whose interval reaches this many days is considered "mature". */
    private const MATURE_INTERVAL = 21;

    public function __construct(private readonly QuranNavigator $navigator) {}

    /**
     * The grade suggested by how a hifz attempt went.
     */
    public function gradeFrom(float $accuracy, int $peeks, int $wordCount): int
    {
        $peekBudget = max(1, intdiv($wordCount, 2));

        return match (true) {
            $accuracy < 80 || $peeks > $peekBudget => self::GRADE_AGAIN,
            $accuracy < 92 || $peeks >= 3 => self::GRADE_HARD,
            $accuracy < 98 || $peeks >= 1 => self::GRADE_GOOD,
            default => self::GRADE_EASY,
        };
    }

    /**
     * Advance one ayah's schedule by a grade (SM-2, lightly tuned). Saves the row.
     */
    public function schedule(UserAyahProgress $progress, int $grade): UserAyahProgress
    {
        $today = $this->today();

        if ($grade === self::GRADE_AGAIN) {
            $progress->reps = 0;
            $progress->lapses += 1;
            $progress->ease = max(1.3, round($progress->ease - 0.2, 2));
            $progress->interval_days = 1;
            $progress->status = 'learning';
        } else {
            $progress->reps += 1;

            $easeDelta = match ($grade) {
                self::GRADE_HARD => -0.15,
                self::GRADE_EASY => 0.15,
                default => 0.0,
            };
            $progress->ease = max(1.3, min(2.7, round($progress->ease + $easeDelta, 2)));

            $progress->interval_days = match (true) {
                $progress->reps === 1 => match ($grade) {
                    self::GRADE_HARD => 1,
                    self::GRADE_EASY => 4,
                    default => 2,
                },
                $progress->reps === 2 => $grade === self::GRADE_HARD ? 3 : 6,
                default => min(365, max(
                    $progress->interval_days + 1,
                    (int) round(
                        $progress->interval_days
                        * $progress->ease
                        * ($grade === self::GRADE_HARD ? 0.8 : 1.0)
                        * ($grade === self::GRADE_EASY ? 1.3 : 1.0)
                    ),
                )),
            };

            $progress->status = $progress->interval_days >= self::MATURE_INTERVAL ? 'review' : 'learning';
        }

        $progress->due_on = $today->addDays($progress->interval_days);
        $progress->last_reviewed_at = now();
        $progress->last_grade = $grade;
        $progress->save();

        return $progress;
    }

    /**
     * Grade every ayah a completed test covered. Returns how many were graded.
     */
    public function gradeTest(Test $test, int $grade): int
    {
        if (! $test->user_id || ! $test->start_ayah || ! $test->end_ayah) {
            return 0;
        }

        $surah = QuranText::whereKey($test->quran_text_id)->value('surah_number');

        if (! $surah) {
            return 0;
        }

        $ayahIds = QuranText::where('surah_number', $surah)
            ->whereBetween('ayah_number', [$test->start_ayah, $test->end_ayah])
            ->pluck('id');

        $today = $this->today()->toDateString();

        foreach ($ayahIds as $id) {
            $progress = UserAyahProgress::firstOrNew(
                ['user_id' => $test->user_id, 'quran_text_id' => $id],
                ['ease' => 2.5, 'due_on' => $today, 'status' => 'learning'],
            );

            $this->schedule($progress, $grade);
        }

        return $ayahIds->count();
    }

    /**
     * Ayahs due for review, oldest first, capped for the day.
     *
     * @return Collection<int, UserAyahProgress>
     */
    public function due(User $user): Collection
    {
        return UserAyahProgress::where('user_id', $user->id)
            ->whereDate('due_on', '<=', $this->today()->toDateString())
            ->with('quranText:id,surah_number,ayah_number,surah_name_english,surah_name_arabic')
            ->orderBy('due_on')
            ->orderBy('quran_text_id')
            ->limit(self::MAX_REVIEWS_PER_DAY)
            ->get();
    }

    /**
     * A passage to review now, opening on the first due ayah.
     *
     * @return array{surah_number:int, start_ayah:int, end_ayah:int}|null
     */
    public function dueSession(User $user): ?array
    {
        $first = $this->due($user)->first();

        return $first
            ? $this->navigator->window($first->quranText->surah_number, $first->quranText->ayah_number)
            : null;
    }

    /**
     * A passage of not-yet-started ayahs, within an optional scope.
     *
     * @return array{surah_number:int, start_ayah:int, end_ayah:int}|null
     */
    public function newSession(User $user, ?string $scope = null, ?int $value = null): ?array
    {
        $tracked = UserAyahProgress::where('user_id', $user->id)->pluck('quran_text_id');

        $query = QuranText::query()->whereNotIn('id', $tracked);

        if ($scope === 'surah' && $value) {
            $query->where('surah_number', $value);
        } elseif ($scope === 'juz' && $value) {
            $query->where('juz', $value);
        }

        $ayah = $query->orderBy('surah_number')->orderBy('ayah_number')
            ->first(['surah_number', 'ayah_number']);

        return $ayah ? $this->navigator->window($ayah->surah_number, $ayah->ayah_number) : null;
    }

    public function dueCount(User $user): int
    {
        return UserAyahProgress::where('user_id', $user->id)
            ->whereDate('due_on', '<=', $this->today()->toDateString())
            ->count();
    }

    /**
     * @return array{learning:int, review:int, due:int, total:int}
     */
    public function stats(User $user): array
    {
        $byStatus = UserAyahProgress::where('user_id', $user->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'learning' => (int) ($byStatus['learning'] ?? 0),
            'review' => (int) ($byStatus['review'] ?? 0),
            'due' => UserAyahProgress::where('user_id', $user->id)
                ->whereDate('due_on', '<=', $this->today()->toDateString())
                ->count(),
            'total' => (int) $byStatus->sum(),
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}
