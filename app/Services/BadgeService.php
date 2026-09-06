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
            'tests-1000' => fn (User $u): bool => $this->testCount($u) >= 1000,

            'wpm-40' => fn (User $u): bool => $this->bestWpm($u) >= 40,
            'wpm-60' => fn (User $u): bool => $this->bestWpm($u) >= 60,
            'wpm-80' => fn (User $u): bool => $this->bestWpm($u) >= 80,
            'perfect-run' => fn (User $u): bool => Test::where('user_id', $u->id)
                ->where('char_count', '>=', 50)
                ->where('incorrect_chars', 0)
                ->exists(),

            'chars-10k' => fn (User $u): bool => $this->totalChars($u) >= 10_000,
            'chars-100k' => fn (User $u): bool => $this->totalChars($u) >= 100_000,

            'streak-7' => fn (User $u): bool => (int) $u->longest_streak >= 7,
            'streak-30' => fn (User $u): bool => (int) $u->longest_streak >= 30,
            'streak-100' => fn (User $u): bool => (int) $u->longest_streak >= 100,

            'first-surah' => fn (User $u): bool => $this->certCount($u) >= 1,
            'certs-5' => fn (User $u): bool => $this->certCount($u) >= 5,
            'certs-10' => fn (User $u): bool => $this->certCount($u) >= 10,
            'juz-amma' => fn (User $u): bool => Certificate::where('user_id', $u->id)
                ->whereBetween('surah_number', [78, 114])
                ->distinct()
                ->count('surah_number') === 37,

            'hifz-10' => fn (User $u): bool => $this->hifzCount($u) >= 10,
            'hifz-50' => fn (User $u): bool => $this->hifzCount($u) >= 50,

            'race-win' => fn (User $u): bool => RaceParticipant::where('user_id', $u->id)->where('position', 1)->exists(),
            'contest-entry' => fn (User $u): bool => Test::where('user_id', $u->id)->where('is_contest_entry', true)->exists(),
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
}
