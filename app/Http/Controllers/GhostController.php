<?php

namespace App\Http\Controllers;

use App\Models\Test;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GhostController extends Controller
{
    /**
     * Return a friend's (or the caller's own PB) recorded run for a ghost race.
     *
     * {ghost} is a Test id, or the literal "pb" for the caller's newest recorded run.
     */
    public function show(Request $request, string $ghost): JsonResponse
    {
        $user = $request->user();

        if ($ghost === 'pb') {
            $test = Test::where('user_id', $user->id)
                ->whereHas('result')
                ->latest()
                ->first();

            abort_if(! $test, 404);
        } else {
            abort_unless(ctype_digit($ghost), 404);

            $test = Test::find((int) $ghost);

            abort_if(! $test, 404);
            abort_unless(
                $test->user_id === $user->id
                    || ($test->user && $user->isFriendsWith($test->user)),
                403,
            );
            abort_if(! $test->result, 404);
        }

        $test->loadMissing('result', 'quranText', 'user');

        return response()->json([
            'surah_number' => $test->quranText->surah_number,
            'start_ayah' => $test->start_ayah,
            'end_ayah' => $test->end_ayah,
            'tashkeel' => (bool) $test->tashkeel,
            'trace' => $test->result->history ?? [],
            'opponent' => [
                'name' => $test->user?->name,
                'wpm' => $test->wpm,
                'is_self' => $test->user_id === $user->id,
            ],
        ]);
    }
}
