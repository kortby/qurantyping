<?php

namespace App\Services;

use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StreakService
{
    /**
     * Record a completed test toward today's activity and advance the user's
     * streak. Guests (no user_id) are ignored.
     *
     * A "day" is the calendar date in config('app.timezone'). The streak
     * survives one missed day per rolling seven days (a weekly rest day).
     */
    public function recordTest(Test $test): void
    {
        if (! $test->user_id) {
            return;
        }

        $today = $this->today();
        $todayStr = $today->toDateString();
        $stamp = now();

        DB::statement(
            'INSERT INTO daily_activity (user_id, `date`, tests_count, chars, seconds, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE tests_count = tests_count + 1, chars = chars + ?, seconds = seconds + ?, updated_at = ?',
            [
                $test->user_id, $todayStr, $test->char_count, $test->duration, $stamp, $stamp,
                $test->char_count, $test->duration, $stamp,
            ]
        );

        $user = User::find($test->user_id);

        if (! $user) {
            return;
        }

        $last = $this->asDate($user->last_practiced_on);

        if ($last?->equalTo($today)) {
            return;
        }

        $graceUsedOn = $this->asDate($user->streak_grace_used_on);
        $graceAvailable = $graceUsedOn === null || $graceUsedOn->lt($today->subDays(7));

        if ($last?->equalTo($today->subDay())) {
            $user->current_streak++;
        } elseif ($last?->equalTo($today->subDays(2)) && $graceAvailable) {
            $user->current_streak++;
            $user->streak_grace_used_on = $todayStr;
        } else {
            $user->current_streak = 1;
        }

        $user->last_practiced_on = $todayStr;
        $user->longest_streak = max((int) $user->longest_streak, (int) $user->current_streak);
        $user->save();
    }

    /**
     * The streak / goal snapshot shared with the frontend. The stored
     * current_streak goes stale once a user lapses without practising, so the
     * value returned here is re-derived against today.
     *
     * @return array{current:int, longest:int, practiced_today:bool, goal:array{target:int, chars_today:int, tests_today:int, met:bool}}
     */
    public function forInertia(User $user): array
    {
        $today = $this->today();
        $last = $this->asDate($user->last_practiced_on);
        $practicedToday = (bool) $last?->equalTo($today);

        $current = (int) $user->current_streak;

        if ($last === null) {
            $current = 0;
        } elseif (! $practicedToday) {
            $graceUsedOn = $this->asDate($user->streak_grace_used_on);
            $graceAvailable = $graceUsedOn === null || $graceUsedOn->lt($today->subDays(7));
            $daysSince = (int) $last->diffInDays($today);

            if ($daysSince > 2 || ($daysSince === 2 && ! $graceAvailable)) {
                $current = 0;
            }
        }

        $row = DB::table('daily_activity')
            ->where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->first();

        $charsToday = (int) ($row->chars ?? 0);
        $target = (int) $user->daily_goal_chars;

        return [
            'current' => $current,
            'longest' => (int) $user->longest_streak,
            'practiced_today' => $practicedToday,
            'goal' => [
                'target' => $target,
                'chars_today' => $charsToday,
                'tests_today' => (int) ($row->tests_count ?? 0),
                'met' => $target > 0 && $charsToday >= $target,
            ],
        ];
    }

    /**
     * Replay a user's ascending, distinct activity dates into a streak state.
     * Used by the backfill migration.
     *
     * @param  list<string>  $dates
     * @return array{current:int, longest:int, last_practiced_on:?string, grace_used_on:?string}
     */
    public static function computeFromDates(array $dates): array
    {
        $tz = config('app.timezone');
        $current = 0;
        $longest = 0;
        $prev = null;
        $graceUsedOn = null;

        foreach ($dates as $date) {
            $day = CarbonImmutable::parse($date, $tz)->startOfDay();

            if ($prev === null) {
                $current = 1;
            } elseif ($day->equalTo($prev->addDay())) {
                $current++;
            } elseif ($day->equalTo($prev->addDays(2)) && ($graceUsedOn === null || $graceUsedOn->lt($day->subDays(7)))) {
                $current++;
                $graceUsedOn = $day;
            } else {
                $current = 1;
            }

            $longest = max($longest, $current);
            $prev = $day;
        }

        if ($prev !== null) {
            $today = CarbonImmutable::now($tz)->startOfDay();
            $graceAvailable = $graceUsedOn === null || $graceUsedOn->lt($today->subDays(7));
            $daysSince = (int) $prev->diffInDays($today);

            if ($daysSince > 2 || ($daysSince === 2 && ! $graceAvailable)) {
                $current = 0;
            }
        }

        return [
            'current' => $current,
            'longest' => $longest,
            'last_practiced_on' => $prev?->toDateString(),
            'grace_used_on' => $graceUsedOn?->toDateString(),
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    private function asDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value, config('app.timezone'))->startOfDay();
    }
}
