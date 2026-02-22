# Ramadan Typing Contest — Implementation Guide

> For [qurantyping.com](https://qurantyping.com) — Laravel App

---

## Overview

Your `tests` table already stores WPM, Raw WPM, Accuracy, `correct_chars`, and `incorrect_chars`. The only thing needed is:

1. A `is_contest_entry` boolean column on the existing `tests` table
2. A `ContestService` that checks if the contest is active and if a test qualifies
3. A Model Observer that sets the flag automatically when a test is saved — zero changes to existing controllers
4. A leaderboard query that filters on that flag

**Key principles:**
- No new tables, no changes to existing controllers
- Feature-flagged via `.env` — safe to deploy anytime
- Observer pattern keeps contest logic fully isolated
- Graceful degradation — when contest is inactive, the flag is simply never set

---

## KPI Definitions

These are the columns already in your `tests` table that will appear on the leaderboard:

| Column | Type | What it means |
|---|---|---|
| `wpm` | integer | Net speed after error penalty — primary ranking metric |
| `raw_wpm` | integer | Pure finger speed before any error penalty |
| `accuracy` | numeric | Percentage of characters typed correctly |
| `correct_chars` | integer | Total characters typed correctly |
| `incorrect_chars` | integer | Total characters typed incorrectly |
| `char_count` | integer | Total characters in the test |
| `duration` | integer | Test duration in seconds |
| `mode` | varchar | Typing mode used (e.g. timed, words) |
| `quran_text_id` | integer | Which Quran passage was typed |

The new column being added:

| Column | Type | What it means |
|---|---|---|
| `is_contest_entry` | boolean | `true` if the test was taken during the contest window and met the minimum WPM + accuracy thresholds |

> Consistency is not in the `tests` table yet. See "To Extend Later" if you want to add it.

---

## 1. `.env` Configuration

Add to your `.env` and `.env.example`:

```env
# Ramadan Contest Settings
CONTEST_ENABLED=false
CONTEST_START_DAY=27
CONTEST_END_DAY=27
CONTEST_MIN_WPM=150
CONTEST_MIN_ACCURACY=98
CONTEST_YEAR=2025
RAMADAN_START_DATE=2025-03-01
```

> Set `CONTEST_ENABLED=false` until you're ready to go live. Update `RAMADAN_START_DATE` each year.

---

## 2. Config File

Create `config/contest.php`:

```php
<?php

return [
    'enabled'            => env('CONTEST_ENABLED', false),
    'start_day'          => env('CONTEST_START_DAY', 27),
    'end_day'            => env('CONTEST_END_DAY', 27),
    'min_wpm'            => env('CONTEST_MIN_WPM', 150),
    'min_accuracy'       => env('CONTEST_MIN_ACCURACY', 98),
    'year'               => env('CONTEST_YEAR', 2025),
    'ramadan_start_date' => env('RAMADAN_START_DATE', '2025-03-01'),
];
```

---

## 3. Migration — Add Column to `tests` Table

```bash
php artisan make:migration add_is_contest_entry_to_tests_table
```

```php
public function up(): void
{
    Schema::table('tests', function (Blueprint $table) {
        // Note: ->after() is not supported in SQLite — column appends to the end
        $table->boolean('is_contest_entry')->default(false);
    });
}

public function down(): void
{
    Schema::table('tests', function (Blueprint $table) {
        $table->dropColumn('is_contest_entry');
    });
}
```

```bash
php artisan migrate
```

---

## 4. ContestService

Create `app/Services/ContestService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ContestService
{
    public function isActive(): bool
    {
        if (!config('contest.enabled')) return false;

        $day = $this->getRamadanDay();
        if (!$day) return false;

        return $day >= config('contest.start_day')
            && $day <= config('contest.end_day');
    }

    public function getRamadanDay(): ?int
    {
        $ramadanStart = Carbon::parse(config('contest.ramadan_start_date'));
        $today = Carbon::today();

        if ($today->lt($ramadanStart)) return null;

        $day = $ramadanStart->diffInDays($today) + 1;

        return ($day >= 1 && $day <= 30) ? $day : null;
    }

    /**
     * Check whether a completed test meets the contest thresholds.
     * Accepts the Test model or a plain array of values.
     */
    public function testQualifies($test): bool
    {
        $wpm      = is_array($test) ? $test['wpm']      : $test->wpm;
        $accuracy = is_array($test) ? $test['accuracy']  : $test->accuracy;

        return $wpm >= config('contest.min_wpm')
            && $accuracy >= config('contest.min_accuracy');
    }

    public function getConfig(): array
    {
        return [
            'active'       => $this->isActive(),
            'min_wpm'      => config('contest.min_wpm'),
            'min_accuracy' => config('contest.min_accuracy'),
            'ramadan_day'  => $this->getRamadanDay(),
        ];
    }
}
```

---

## 5. Model Observer — Auto-flag Qualifying Tests

This is the key piece. It hooks into the `tests` table save event and sets `is_contest_entry` automatically — no changes to any existing controller needed.

```bash
php artisan make:observer TestObserver --model=Test
```

Edit `app/Observers/TestObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Test;
use App\Services\ContestService;

class TestObserver
{
    public function __construct(private ContestService $contest) {}

    public function creating(Test $test): void
    {
        // Evaluate before the record is inserted
        $test->is_contest_entry = $this->contest->isActive()
            && $this->contest->testQualifies($test);
    }
}
```

Register the observer in `app/Providers/AppServiceProvider.php`:

```php
use App\Models\Test;
use App\Observers\TestObserver;

public function boot(): void
{
    Test::observe(TestObserver::class);
}
```

> That's it for the backend. Every time a test is saved, the observer silently sets `is_contest_entry = true/false`. Your existing save logic is completely untouched.

---

## 6. Update the Test Model

In your existing `Test` model, add `is_contest_entry` to `$fillable` and cast it:

```php
protected $fillable = [
    'user_id',
    'quran_text_id',
    'mode',
    'duration',
    'wpm',
    'raw_wpm',
    'accuracy',
    'char_count',
    'correct_chars',
    'incorrect_chars',
    'is_contest_entry',  // ← add this
];

protected $casts = [
    'is_contest_entry' => 'boolean',
];
```

Also add a scope for convenient querying:

```php
public function scopeContestEntries($query)
{
    return $query->where('is_contest_entry', true);
}
```

---

## 7. ContestController

```bash
php artisan make:controller ContestController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\ContestService;

class ContestController extends Controller
{
    public function __construct(private ContestService $contest) {}

    public function index()
    {
        if (!$this->contest->isActive()) {
            return view('contest.inactive');
        }

        $leaderboard = Test::contestEntries()
            ->with('user')
            ->orderByDesc('wpm')
            ->orderByDesc('accuracy')
            ->take(50)
            ->get();

        return view('contest.index', [
            'leaderboard' => $leaderboard,
            'config'      => $this->contest->getConfig(),
        ]);
    }
}
```

No POST route needed — submission is handled automatically by the observer when a test is saved.

---

## 8. Routes

Add to `routes/web.php`:

```php
Route::get('/contest', [ContestController::class, 'index'])->name('contest.index');
```

---

## 9. Views

### `resources/views/contest/inactive.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="text-center py-20">
    <h1 class="text-2xl font-bold">Contest is not active</h1>
    <p class="text-gray-500 mt-2">
        The Ramadan typing contest opens on the {{ config('contest.start_day') }}th night. Stay tuned!
    </p>
</div>
@endsection
```

### `resources/views/contest/index.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-10">
    <h1 class="text-3xl font-bold mb-2">🌙 Ramadan Typing Contest</h1>
    <p class="text-gray-500 mb-6">
        Minimum {{ $config['min_wpm'] }} WPM &middot; {{ $config['min_accuracy'] }}% accuracy required
    </p>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-gray-100">
                <th class="p-3">#</th>
                <th class="p-3">Name</th>
                <th class="p-3">WPM</th>
                <th class="p-3">Raw WPM</th>
                <th class="p-3">Accuracy</th>
                <th class="p-3">Correct</th>
                <th class="p-3">Incorrect</th>
                <th class="p-3">Chars</th>
                <th class="p-3">Duration</th>
            </tr>
        </thead>
        <tbody>
            @foreach($leaderboard as $i => $entry)
            <tr class="border-t">
                <td class="p-3">{{ $i + 1 }}</td>
                <td class="p-3">{{ $entry->user->name }}</td>
                <td class="p-3 font-bold">{{ $entry->wpm }}</td>
                <td class="p-3 text-gray-500">{{ $entry->raw_wpm }}</td>
                <td class="p-3">{{ $entry->accuracy }}%</td>
                <td class="p-3 text-green-600">{{ $entry->correct_chars }}</td>
                <td class="p-3 text-red-500">{{ $entry->incorrect_chars }}</td>
                <td class="p-3">{{ $entry->char_count }}</td>
                <td class="p-3">{{ $entry->duration }}s</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
```

---

## Deployment Checklist

```
☐ Add .env variables (CONTEST_ENABLED=false to start)
☐ Update .env.example
☐ Create config/contest.php
☐ php artisan make:migration add_is_contest_entry_to_tests_table
☐ php artisan migrate
☐ Create app/Services/ContestService.php
☐ php artisan make:observer TestObserver --model=Test
☐ Register observer in AppServiceProvider::boot()
☐ Add is_contest_entry to Test model $fillable, $casts, and scopeContestEntries()
☐ Create app/Http/Controllers/ContestController.php
☐ Add route to routes/web.php
☐ Create resources/views/contest/inactive.blade.php
☐ Create resources/views/contest/index.blade.php
☐ php artisan config:cache
☐ Set CONTEST_ENABLED=true on the 27th night 🌙
```

---

## To Extend Later

- **Consistency** — add a `consistency` decimal column to `tests`, compute it on the frontend, send it with the result, then add it to `testQualifies()` and the leaderboard
- **Best score only** — add `.groupBy('user_id')` or a `DISTINCT ON user_id` subquery to show only each user's best test
- **Email notification** — fire an event in the observer when `is_contest_entry` is true so the user gets a congratulations email
- **Export** — add a CSV export route on the leaderboard for prize distribution
