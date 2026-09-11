<?php

namespace App\Services;

use App\Http\Controllers\SurahController;
use App\Models\ClassAssignment;
use App\Models\ClassGroup;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ClassService
{
    public function __construct(
        private readonly StreakService $streaks,
        private readonly HifzService $hifz,
    ) {}

    /**
     * Open a new class the given user teaches, with a fresh join code.
     */
    public function create(User $owner, string $name): ClassGroup
    {
        return $owner->ownedClasses()->create([
            'name' => $name,
            'code' => $this->uniqueCode(),
        ]);
    }

    /**
     * Resolve a join code ("ABC123") to a class.
     */
    public function resolve(string $code): ?ClassGroup
    {
        return ClassGroup::where('code', Str::upper($code))->first();
    }

    /**
     * Join a class as a student. Idempotent — re-joining is a no-op.
     */
    public function join(ClassGroup $class, User $user): void
    {
        if ($class->members()->where('user_id', $user->id)->exists()) {
            return;
        }

        $class->members()->attach($user->id, ['joined_at' => now()]);
    }

    /**
     * Per-student progress summary for a teacher's roster.
     *
     * @return list<array<string, mixed>>
     */
    public function rosterFor(ClassGroup $class): array
    {
        return $class->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'joined_at' => Carbon::parse($student->pivot->joined_at)->toIso8601String(),
                ...$this->progressFor($student),
            ])
            ->all();
    }

    /**
     * The subset of a student's stats a teacher cares about.
     *
     * @return array<string, mixed>
     */
    public function progressFor(User $student): array
    {
        $tests = $student->tests();

        return [
            'tests_count' => (int) $tests->clone()->count(),
            'avg_wpm' => (int) round((float) $tests->clone()->avg('wpm')),
            'avg_accuracy' => round((float) $tests->clone()->avg('accuracy'), 1),
            'last_practiced_on' => $student->last_practiced_on,
            'streak' => $this->streaks->forInertia($student)['current'],
            'hifz_due' => $this->hifz->stats($student)['due'],
        ];
    }

    /**
     * When a student joined a class, as an ISO 8601 string.
     */
    public function joinedAtFor(ClassGroup $class, User $student): ?string
    {
        $member = $class->members()->where('user_id', $student->id)->first();

        return $member ? Carbon::parse($member->pivot->joined_at)->toIso8601String() : null;
    }

    /**
     * A fuller stats breakdown for a single student's detail page.
     *
     * @return array<string, mixed>
     */
    public function detailedProgressFor(User $student): array
    {
        $tests = $student->tests();

        return [
            'tests_count' => (int) $tests->clone()->count(),
            'best_wpm' => (int) $tests->clone()->max('wpm'),
            'avg_wpm' => (int) round((float) $tests->clone()->avg('wpm')),
            'avg_accuracy' => round((float) $tests->clone()->avg('accuracy'), 1),
            'total_chars' => (int) $tests->clone()->sum('char_count'),
            'total_errors' => (int) $tests->clone()->sum('total_errors'),
            'first_test_at' => ($firstTestAt = $tests->clone()->min('created_at'))
                ? Carbon::parse($firstTestAt)->toIso8601String()
                : null,
            'last_practiced_on' => $student->last_practiced_on,
            'streak' => $this->streaks->forInertia($student),
            'hifz' => $this->hifz->stats($student),
            'recent_tests' => $student->tests()
                ->with('quranText:id,surah_number')
                ->latest()
                ->take(10)
                ->get(['id', 'quran_text_id', 'wpm', 'accuracy', 'char_count', 'total_errors', 'mode', 'hifz_level', 'start_ayah', 'end_ayah', 'created_at'])
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'wpm' => (int) $t->wpm,
                    'accuracy' => (float) $t->accuracy,
                    'char_count' => (int) $t->char_count,
                    'total_errors' => (int) $t->total_errors,
                    'mode' => $t->hifz_level ? 'hifz' : $t->mode,
                    'range' => $t->quranText ? $t->quranText->surah_number.':'.$t->start_ayah.'–'.$t->end_ayah : null,
                    'created_at' => $t->created_at->toIso8601String(),
                ]),
        ];
    }

    /**
     * Assign a surah range for the class to practise.
     */
    public function createAssignment(ClassGroup $class, int $surahNumber, int $startAyah, int $endAyah, ?string $dueOn): ClassAssignment
    {
        return $class->assignments()->create([
            'surah_number' => $surahNumber,
            'start_ayah' => $startAyah,
            'end_ayah' => $endAyah,
            'due_on' => $dueOn,
        ]);
    }

    /**
     * This class's assignments, enriched with the surah's name and a deep
     * link straight into that range on the homepage. Pass `$enrich` to add
     * role-specific fields (e.g. completion) while the model is still in
     * scope, rather than re-fetching each assignment afterwards.
     *
     * @param  (callable(ClassAssignment): array<string, mixed>)|null  $enrich
     * @return list<array<string, mixed>>
     */
    public function assignmentsFor(ClassGroup $class, ?callable $enrich = null): array
    {
        $surahIndex = SurahController::all()->keyBy('surah_number');

        return $class->assignments()
            ->latest()
            ->get()
            ->map(function (ClassAssignment $assignment) use ($surahIndex, $enrich): array {
                $surah = $surahIndex->get($assignment->surah_number);

                $data = [
                    'id' => $assignment->id,
                    'surah_number' => $assignment->surah_number,
                    'surah_name' => $surah['name_english'] ?? "Surah {$assignment->surah_number}",
                    'start_ayah' => $assignment->start_ayah,
                    'end_ayah' => $assignment->end_ayah,
                    'due_on' => $assignment->due_on?->toDateString(),
                    'start_url' => "/?surah={$assignment->surah_number}&start={$assignment->start_ayah}&end={$assignment->end_ayah}",
                    'created_at' => $assignment->created_at->toIso8601String(),
                ];

                return $enrich ? [...$data, ...$enrich($assignment)] : $data;
            })
            ->all();
    }

    /**
     * Whether a student has typed anything from the assigned surah since it
     * was assigned. A loose definition on purpose — no strict ayah-range
     * matching, so practising the surah in more than one sitting still counts.
     */
    public function hasCompletedAssignment(ClassAssignment $assignment, User $student): bool
    {
        return Test::where('user_id', $student->id)
            ->whereHas('quranText', fn ($query) => $query->where('surah_number', $assignment->surah_number))
            ->where('created_at', '>=', $assignment->created_at)
            ->exists();
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (ClassGroup::where('code', $code)->exists());

        return $code;
    }
}
