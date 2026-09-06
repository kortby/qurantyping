<?php

namespace App\Http\Controllers;

use App\Models\DailyChallengeRun;
use App\Services\DailyChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyChallengeController extends Controller
{
    public function __construct(private readonly DailyChallengeService $daily) {}

    /**
     * The /today page — the passage, your result, and a friends mini-board.
     */
    public function index(Request $request): Response
    {
        $challenge = $this->daily->today();
        $user = $request->user();

        $mine = DailyChallengeRun::where('user_id', $user->id)
            ->where('challenge_date', $challenge['date'])
            ->first();

        $ids = $user->friendIds()->push($user->id);

        $board = DailyChallengeRun::query()
            ->whereIn('daily_challenge_runs.user_id', $ids)
            ->where('challenge_date', $challenge['date'])
            ->join('users', 'users.id', '=', 'daily_challenge_runs.user_id')
            ->orderByDesc('daily_challenge_runs.wpm')
            ->orderByDesc('daily_challenge_runs.accuracy')
            ->get([
                'daily_challenge_runs.user_id',
                'users.name',
                'daily_challenge_runs.wpm',
                'daily_challenge_runs.accuracy',
            ])
            ->map(fn ($row): array => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'wpm' => (int) $row->wpm,
                'accuracy' => round((float) $row->accuracy),
                'is_me' => $row->user_id === $user->id,
            ])
            ->all();

        return Inertia::render('Today/Index', [
            'challenge' => $challenge,
            'mine' => $mine ? [
                'wpm' => $mine->wpm,
                'accuracy' => round($mine->accuracy),
            ] : null,
            'board' => $board,
        ]);
    }

    /**
     * Today's passage as JSON, for the typing screen (guests included).
     */
    public function text(): JsonResponse
    {
        return response()->json($this->daily->today());
    }
}
