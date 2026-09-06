<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Certificate;
use App\Models\RaceParticipant;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * slug => rule. Every rule is a cheap aggregate over the user's data.
     *
     * @return array<string, callable(User): bool>
     */
    private function rules(): array
    {
        return [
            'first-test' => fn (User $u): bool => $this->testCount($u) >= 1,
            'tests-10' => fn (User $u): bool => $this->testCount($u) >= 10,
            'tests-100' => fn (User $u): bool => $this->testCount($u) >= 100,
            'tests-250' => fn (User $u): bool => $this->testCount($u) >= 250,
            'tests-500' => fn (User $u): bool => $this->testCount($u) >= 500,
            'tests-1000' => fn (User $u): bool => $this->testCount($u) >= 1000,

            'wpm-40' => fn (User $u): bool => $this->bestWpm($u) >= 40,
            'wpm-60' => fn (User $u): bool => $this->bestWpm($u) >= 60,
            'wpm-80' => fn (User $u): bool => $this->bestWpm($u) >= 80,
            'wpm-100' => fn (User $u): bool => $this->bestWpm($u) >= 100,
            'perfect-run' => fn (User $u): bool => $this->perfectRuns($u) >= 1,
            'perfectionist' => fn (User $u): bool => $this->perfectRuns($u) >= 10,
            'marathon' => fn (User $u): bool => Test::where('user_id', $u->id)->where('char_count', '>=', 500)->exists(),

            'chars-10k' => fn (User $u): bool => $this->totalChars($u) >= 10_000,
            'chars-100k' => fn (User $u): bool => $this->totalChars($u) >= 100_000,
            'chars-500k' => fn (User $u): bool => $this->totalChars($u) >= 500_000,

            'streak-7' => fn (User $u): bool => (int) $u->longest_streak >= 7,
            'streak-14' => fn (User $u): bool => (int) $u->longest_streak >= 14,
            'streak-30' => fn (User $u): bool => (int) $u->longest_streak >= 30,
            'streak-100' => fn (User $u): bool => (int) $u->longest_streak >= 100,
            'streak-365' => fn (User $u): bool => (int) $u->longest_streak >= 365,

            'days-30' => fn (User $u): bool => $this->activeDays($u) >= 30,
            'days-100' => fn (User $u): bool => $this->activeDays($u) >= 100,
            'goal-7' => fn (User $u): bool => $this->goalDays($u) >= 7,
            'goal-30' => fn (User $u): bool => $this->goalDays($u) >= 30,

            'first-surah' => fn (User $u): bool => $this->certCount($u) >= 1,
            'certs-5' => fn (User $u): bool => $this->certCount($u) >= 5,
            'certs-10' => fn (User $u): bool => $this->certCount($u) >= 10,
            'certs-25' => fn (User $u): bool => $this->certCount($u) >= 25,
            'juz-amma' => fn (User $u): bool => Certificate::where('user_id', $u->id)
                ->whereBetween('surah_number', [78, 114])
                ->distinct()
                ->count('surah_number') === 37,
            'juz-5' => fn (User $u): bool => $this->certifiedJuz($u) >= 5,
            'juz-15' => fn (User $u): bool => $this->certifiedJuz($u) >= 15,
            'juz-30' => fn (User $u): bool => $this->certifiedJuz($u) >= 30,

            'first-hifz' => fn (User $u): bool => Test::where('user_id', $u->id)->whereNotNull('hifz_level')->exists(),
            'hifz-10' => fn (User $u): bool => $this->hifzCount($u) >= 10,
            'hifz-50' => fn (User $u): bool => $this->hifzCount($u) >= 50,
            'hifz-100' => fn (User $u): bool => $this->hifzCount($u) >= 100,
            'hifz-300' => fn (User $u): bool => $this->hifzCount($u) >= 300,

            'race-win' => fn (User $u): bool => RaceParticipant::where('user_id', $u->id)->where('position', 1)->exists(),
            'podium' => fn (User $u): bool => RaceParticipant::where('user_id', $u->id)->whereBetween('position', [1, 3])->exists(),
            'race-10' => fn (User $u): bool => RaceParticipant::where('user_id', $u->id)->whereNotNull('finished_at')->count() >= 10,
            'rivals-5' => fn (User $u): bool => $this->distinctRivals($u) >= 5,
            'contest-entry' => fn (User $u): bool => Test::where('user_id', $u->id)->where('is_contest_entry', true)->exists(),

            'first-tashkeel' => fn (User $u): bool => $this->tashkeelCount($u) >= 1,
            'tashkeel-25' => fn (User $u): bool => $this->tashkeelCount($u) >= 25,
            'tashkeel-100' => fn (User $u): bool => $this->tashkeelCount($u) >= 100,
            'tashkeel-perfect' => fn (User $u): bool => Test::where('user_id', $u->id)
                ->where('tashkeel', true)
                ->where('char_count', '>=', 50)
                ->where('incorrect_chars', 0)
                ->exists(),
        ];
    }

    /**
     * Award any newly-earned badges. Returns the Badge models just awarded.
     *
     * @return Collection<int, Badge>
     */
    public function evaluate(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $earned = $user->badges()->pluck('badges.slug')->flip();
        $bySlug = Badge::whereNotNull('slug')->get()->keyBy('slug');
        $awarded = collect();

        foreach ($this->rules() as $slug => $rule) {
            if ($earned->has($slug) || ! isset($bySlug[$slug])) {
                continue;
            }

            if ($rule($user)) {
                $user->badges()->syncWithoutDetaching([$bySlug[$slug]->id => ['awarded_at' => now()]]);
                $awarded->push($bySlug[$slug]);
            }
        }

        return $awarded;
    }

    /**
     * Every catalogue badge with the user's earned date (null if not earned).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user): Collection
    {
        $earnedAt = $user->badges()->pluck('user_badges.awarded_at', 'badges.slug');

        return collect(config('badges.list'))->map(function (array $b) use ($earnedAt): array {
            $at = $earnedAt->get($b['slug']);

            return [
                'slug' => $b['slug'],
                'name' => $b['name'],
                'description' => $b['description'],
                'icon' => $b['icon'],
                'tier' => $b['tier'],
                'earned_at' => $at ? Carbon::parse($at)->toIso8601String() : null,
            ];
        })->values();
    }

    private function testCount(User $u): int
    {
        return Test::where('user_id', $u->id)->count();
    }

    private function bestWpm(User $u): int
    {
        return (int) Test::where('user_id', $u->id)->max('wpm');
    }

    private function totalChars(User $u): int
    {
        return (int) Test::where('user_id', $u->id)->sum('char_count');
    }

    private function certCount(User $u): int
    {
        return Certificate::where('user_id', $u->id)->count();
    }

    private function hifzCount(User $u): int
    {
        return UserAyahProgress::where('user_id', $u->id)->count();
    }

    private function perfectRuns(User $u): int
    {
        return Test::where('user_id', $u->id)
            ->where('char_count', '>=', 50)
            ->where('incorrect_chars', 0)
            ->count();
    }

    private function activeDays(User $u): int
    {
        return DB::table('daily_activity')->where('user_id', $u->id)->count();
    }

    private function goalDays(User $u): int
    {
        return DB::table('daily_activity')
            ->where('user_id', $u->id)
            ->where('chars', '>=', (int) $u->daily_goal_chars)
            ->count();
    }

    /** Distinct juz spanned by the ayahs of the user's certified surahs. */
    private function certifiedJuz(User $u): int
    {
        return (int) DB::table('certificates as c')
            ->join('quran_texts as qt', 'qt.surah_number', '=', 'c.surah_number')
            ->where('c.user_id', $u->id)
            ->distinct()
            ->count('qt.juz');
    }

    private function tashkeelCount(User $u): int
    {
        return Test::where('user_id', $u->id)->where('tashkeel', true)->count();
    }

    private function distinctRivals(User $u): int
    {
        $raceIds = RaceParticipant::where('user_id', $u->id)->pluck('race_id');

        if ($raceIds->isEmpty()) {
            return 0;
        }

        return (int) RaceParticipant::whereIn('race_id', $raceIds)
            ->where('user_id', '!=', $u->id)
            ->distinct()
            ->count('user_id');
    }
}
