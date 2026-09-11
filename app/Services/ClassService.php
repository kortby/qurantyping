<?php

namespace App\Services;

use App\Models\ClassGroup;
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

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (ClassGroup::where('code', $code)->exists());

        return $code;
    }
}
