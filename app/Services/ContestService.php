<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ContestService
{
    public function isActive(): bool
    {
        if (!config('contest.enabled'))
            return false;

        $now = Carbon::now();

        $start = $this->getStartTime();
        $end = $this->getEndTime();

        if (!$start || !$end)
            return false;

        return $now->between($start, $end);
    }

    public function getStartTime(): ?Carbon
    {
        $startDate = config('contest.ramadan_start_date');
        if (!$startDate)
            return null;

        // Mid 26th day: 25 full days + 12 hours from the exact start of Ramadan
        return Carbon::parse($startDate)->addDays(25)->addHours(12);
    }

    public function getEndTime(): ?Carbon
    {
        $startDate = config('contest.ramadan_start_date');
        if (!$startDate)
            return null;

        // Mid 28th day: 27 full days + 12 hours from the exact start of Ramadan
        return Carbon::parse($startDate)->addDays(27)->addHours(12);
    }

    public function get27thNightTarget(): ?Carbon
    {
        $startDate = config('contest.ramadan_start_date');
        if (!$startDate)
            return null;

        // Let's define the 27th night focal point as Sunset of the 26th day.
        // For a global reference, we'll arbitrarily use 18:00 UTC (6 PM) on the 26th day.
        // That is 25 full days + 18 hours.
        return Carbon::parse($startDate)->addDays(25)->addHours(18);
    }

    public function getRamadanDay(): ?int
    {
        $ramadanStart = Carbon::parse(config('contest.ramadan_start_date'));
        $today = Carbon::now();

        if ($today->lt($ramadanStart))
            return null;

        $day = $ramadanStart->diffInDays($today) + 1;

        return ($day >= 1 && $day <= 30) ? $day : null;
    }

    /**
     * Check whether a completed test meets the contest thresholds.
     * Accepts the Test model or a plain array of values.
     */
    public function testQualifies($test): bool
    {
        $wpm = is_array($test) ? $test['wpm'] : $test->wpm;
        $accuracy = is_array($test) ? $test['accuracy'] : $test->accuracy;

        return $wpm >= config('contest.min_wpm')
            && $accuracy >= config('contest.min_accuracy');
    }

    public function getConfig(): array
    {
        return [
            'active' => $this->isActive(),
            'min_wpm' => config('contest.min_wpm'),
            'min_accuracy' => config('contest.min_accuracy'),
            'ramadan_day' => $this->getRamadanDay(),
            'start_time' => $this->getStartTime()?->toIso8601String(),
            'end_time' => $this->getEndTime()?->toIso8601String(),
            'target_27th_night' => $this->get27thNightTarget()?->toIso8601String(),
            'enabled' => config('contest.enabled'),
        ];
    }
}
