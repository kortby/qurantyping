<?php

namespace App\Http\Controllers;

use App\Models\UserLetterStat;
use App\Services\WeakLetterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DrillController extends Controller
{
    public function __invoke(Request $request, WeakLetterService $weakLetters): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('Drills/Index', [
            'ready' => $weakLetters->weakChars($userId)->isNotEmpty(),
            'minAttempts' => (int) config('drills.min_attempts'),
            'letters' => fn () => UserLetterStat::query()
                ->where('user_id', $userId)
                ->where('attempts', '>=', 1)
                ->get()
                ->map(fn (UserLetterStat $stat): array => [
                    'character' => $stat->character,
                    'attempts' => (int) $stat->attempts,
                    'misses' => (int) $stat->misses,
                    'accuracy' => round(100 * (1 - (int) $stat->misses / (int) $stat->attempts), 1),
                ])
                ->sortBy('accuracy')
                ->values(),
            'weak' => fn () => $weakLetters->weakChars($userId)->map(fn (array $row): array => [
                'character' => $row['character'],
                'attempts' => $row['attempts'],
                'accuracy' => round(100 * (1 - $row['miss_rate']), 1),
            ]),
        ]);
    }
}
