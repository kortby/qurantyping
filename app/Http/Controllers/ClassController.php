<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
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
