<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\HifzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HifzController extends Controller
{
    public function __construct(private readonly HifzService $hifz) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $due = $this->hifz->due($user)
            ->groupBy(fn ($p) => $p->quranText->surah_name_english)
            ->map(fn ($group, $name) => [
                'surah' => $name,
                'surah_arabic' => $group->first()->quranText->surah_name_arabic,
                'ayahs' => $group->map(fn ($p) => $p->quranText->ayah_number)->values(),
            ])
            ->values();

        return Inertia::render('Hifz/Index', [
            'stats' => $this->hifz->stats($user),
            'due' => $due,
            'daily_new' => (int) $user->hifz_daily_new,
            'has_new' => $this->hifz->newSession($user) !== null,
        ]);
    }

    /**
     * Resolve the next hifz passage. The client then loads its text from
     * /api/test/text with the returned range.
     */
    public function session(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['due', 'new'])],
            'scope' => ['sometimes', Rule::in(['surah', 'juz'])],
            'value' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $location = $validated['mode'] === 'due'
            ? $this->hifz->dueSession($user)
            : $this->hifz->newSession($user, $validated['scope'] ?? null, isset($validated['value']) ? (int) $validated['value'] : null);

        if (! $location) {
            return response()->json([
                'message' => $validated['mode'] === 'due'
                    ? 'Nothing is due for review right now.'
                    : 'You have started every ayah in that selection.',
            ], 404);
        }

        return response()->json($location + ['mode' => $validated['mode']]);
    }

    public function grade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_id' => ['required', 'integer', 'exists:tests,id'],
            'grade' => ['required', 'integer', 'between:0,3'],
        ]);

        $test = Test::findOrFail($validated['test_id']);

        abort_unless($test->user_id === $request->user()->id, 403);

        $graded = $this->hifz->gradeTest($test, $validated['grade']);

        return response()->json([
            'graded' => $graded,
            'due' => $this->hifz->stats($request->user())['due'],
        ]);
    }
}
