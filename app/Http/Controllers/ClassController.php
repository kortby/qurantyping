<?php

namespace App\Http\Controllers;

use App\Models\ClassAssignment;
use App\Models\ClassGroup;
use App\Models\User;
use App\Services\ClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function __construct(private readonly ClassService $classes) {}

    /**
     * Classes this user teaches, and classes they've joined as a student.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Classes/Index', [
            'owned' => $user->ownedClasses()
                ->withCount('members')
                ->latest()
                ->get()
                ->map(fn (ClassGroup $class): array => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'code' => $class->code,
                    'members_count' => $class->members_count,
                ])->all(),
            'joined' => $user->classMemberships()
                ->with('owner:id,name')
                ->get()
                ->map(fn (ClassGroup $class): array => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'teacher' => $class->owner->name,
                ])->all(),
        ]);
    }

    /**
     * Open a new class the current user teaches.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $class = $this->classes->create($request->user(), $validated['name']);

        return redirect()->route('classes.show', $class);
    }

    /**
     * The teacher's roster, or a student's own progress in the class.
     */
    public function show(Request $request, ClassGroup $class): Response
    {
        $user = $request->user();
        $isOwner = $class->owner_user_id === $user->id;

        abort_unless($isOwner || $class->members()->where('user_id', $user->id)->exists(), 403);

        if ($isOwner) {
            $members = $class->members;
            $assignments = $this->classes->assignmentsFor($class, fn (ClassAssignment $a): array => [
                'completed_count' => $members->filter(fn (User $m) => $this->classes->hasCompletedAssignment($a, $m))->count(),
                'members_count' => $members->count(),
            ]);
        } else {
            $assignments = $this->classes->assignmentsFor($class, fn (ClassAssignment $a): array => [
                'completed' => $this->classes->hasCompletedAssignment($a, $user),
            ]);
        }

        return Inertia::render('Classes/Show', [
            'group' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'join_url' => url('/c-join/'.$class->code),
            ],
            'isOwner' => $isOwner,
            'roster' => $isOwner ? $this->classes->rosterFor($class) : [],
            'myProgress' => $isOwner ? null : $this->classes->progressFor($user),
            'assignments' => $assignments,
        ]);
    }

    /**
     * Assign a surah range for the class to practise.
     */
    public function storeAssignment(Request $request, ClassGroup $class): RedirectResponse
    {
        abort_unless($class->owner_user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'surah_number' => ['required', 'integer', 'min:1', 'max:114'],
            'start_ayah' => ['required', 'integer', 'min:1'],
            'end_ayah' => ['required', 'integer', 'min:1', 'gte:start_ayah'],
            'due_on' => ['nullable', 'date'],
        ]);

        $this->classes->createAssignment(
            $class,
            $validated['surah_number'],
            $validated['start_ayah'],
            $validated['end_ayah'],
            $validated['due_on'] ?? null,
        );

        return back();
    }

    /**
     * Remove an assignment from the class.
     */
    public function destroyAssignment(Request $request, ClassGroup $class, ClassAssignment $assignment): RedirectResponse
    {
        abort_unless($class->owner_user_id === $request->user()->id, 403);
        abort_unless($assignment->class_id === $class->id, 404);

        $assignment->delete();

        return back();
    }

    /**
     * A teacher's detailed view of one student's progress in the class.
     */
    public function student(Request $request, ClassGroup $class, User $student): Response
    {
        abort_unless($class->owner_user_id === $request->user()->id, 403);
        abort_unless($class->members()->where('user_id', $student->id)->exists(), 404);

        return Inertia::render('Classes/Student', [
            'group' => [
                'id' => $class->id,
                'name' => $class->name,
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'joined_at' => $this->classes->joinedAtFor($class, $student),
            ],
            'progress' => $this->classes->detailedProgressFor($student),
            'assignments' => $this->classes->assignmentsFor($class, fn (ClassAssignment $a): array => [
                'completed' => $this->classes->hasCompletedAssignment($a, $student),
            ]),
        ]);
    }

    /**
     * Land on a class join link — join immediately, handing guests through
     * registration first.
     */
    public function join(Request $request, string $code): RedirectResponse
    {
        $class = $this->classes->resolve($code);

        abort_if(! $class, 404);

        if (! $request->user()) {
            $request->session()->put('pending_class_code', $class->code);

            return redirect()->route('register');
        }

        $this->classes->join($class, $request->user());

        return redirect()->route('classes.show', $class);
    }

    /**
     * Close a class the current user teaches. Members are cascade-deleted.
     */
    public function destroy(Request $request, ClassGroup $class): RedirectResponse
    {
        abort_unless($class->owner_user_id === $request->user()->id, 403);

        $class->delete();

        return redirect()->route('classes.index')->with('message', __('Class deleted.'));
    }
}
