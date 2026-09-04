<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    /**
     * @var list<string>
     */
    protected array $types = ['bug', 'suggestion', 'other'];

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('filter'), ['open', 'handled', 'all'], true)
            ? $request->query('filter')
            : 'open';

        $type = in_array($request->query('type'), $this->types, true)
            ? $request->query('type')
            : null;

        return Inertia::render('Admin/Feedback/Index', [
            'filters' => ['filter' => $filter, 'type' => $type],
            'counts' => fn (): array => [
                'all' => Feedback::query()->count(),
                'open' => Feedback::query()->whereNull('handled_at')->count(),
                'handled' => Feedback::query()->whereNotNull('handled_at')->count(),
            ],
            'feedback' => fn () => Feedback::query()
                ->with('user:id,name,email')
                ->when($filter === 'open', fn ($query) => $query->whereNull('handled_at'))
                ->when($filter === 'handled', fn ($query) => $query->whereNotNull('handled_at'))
                ->when($type, fn ($query) => $query->where('type', $type))
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Feedback $item): array => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'excerpt' => Str::limit($item->message, 160),
                    'handled_at' => $item->handled_at?->toIso8601String(),
                    'created_at' => $item->created_at->toIso8601String(),
                    'user' => $item->user ? [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ] : null,
                ]),
        ]);
    }

    public function show(Feedback $feedback): Response
    {
        $feedback->load('user:id,name,email');

        return Inertia::render('Admin/Feedback/Show', [
            'feedback' => [
                'id' => $feedback->id,
                'type' => $feedback->type,
                'message' => $feedback->message,
                'handled_at' => $feedback->handled_at?->toIso8601String(),
                'created_at' => $feedback->created_at->toIso8601String(),
                'user' => $feedback->user ? [
                    'id' => $feedback->user->id,
                    'name' => $feedback->user->name,
                    'email' => $feedback->user->email,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $feedback->forceFill([
            'handled_at' => $request->boolean('handled') ? now() : null,
        ])->save();

        return back()->with('message', $feedback->handled_at ? 'Marked as handled.' : 'Reopened.');
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return redirect()->route('admin.feedback.index')->with('message', 'Feedback deleted.');
    }
}
