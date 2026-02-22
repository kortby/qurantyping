<?php

use App\Services\ContestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\travelBack;

beforeEach(function () {
    Config::set('contest.enabled', true);
    Config::set('contest.ramadan_start_date', '2026-02-19T00:00:00Z');
    Config::set('contest.min_wpm', 150);
    Config::set('contest.min_accuracy', 98);
});

afterEach(function () {
    travelBack();
});

it('calculates the current ramadan day correctly', function () {
    $service = new ContestService();

    travelTo(Carbon::parse('2026-02-19T10:00:00Z'));
    expect($service->getRamadanDay())->toBe(1);

    travelTo(Carbon::parse('2026-02-21T10:00:00Z'));
    expect($service->getRamadanDay())->toBe(3);

    travelTo(Carbon::parse('2026-03-16T10:00:00Z'));
    expect($service->getRamadanDay())->toBe(26);
});

it('determines the contest is inactive before the 26th day window', function () {
    $service = new ContestService();

    // Day 26 starts at 2026-03-16T00:00:00Z. The 12-hour mark is 12:00:00Z.
    travelTo(Carbon::parse('2026-03-16T10:00:00Z'));

    expect($service->isActive())->toBeFalse();
});

it('activates the contest exactly at 12 hours into the 26th day', function () {
    $service = new ContestService();

    travelTo(Carbon::parse('2026-03-16T13:00:00Z'));

    expect($service->isActive())->toBeTrue();
});

it('keeps the contest active on the 27th day', function () {
    $service = new ContestService();

    travelTo(Carbon::parse('2026-03-17T12:00:00Z'));

    expect($service->isActive())->toBeTrue();
});

it('closes the contest exactly 48 hours later', function () {
    $service = new ContestService();

    // Day 28, 12-hour mark is 2026-03-18T12:00:00Z. 
    // 13:00:00Z is after the contest closes.
    travelTo(Carbon::parse('2026-03-18T13:00:00Z'));

    expect($service->isActive())->toBeFalse();
});

it('verifies score qualification against contest rules', function () {
    $service = new ContestService();

    expect($service->testQualifies(['wpm' => 160, 'accuracy' => 99]))->toBeTrue();
    expect($service->testQualifies(['wpm' => 150, 'accuracy' => 98]))->toBeTrue();
    expect($service->testQualifies(['wpm' => 140, 'accuracy' => 99]))->toBeFalse();
    expect($service->testQualifies(['wpm' => 160, 'accuracy' => 90]))->toBeFalse();
});
