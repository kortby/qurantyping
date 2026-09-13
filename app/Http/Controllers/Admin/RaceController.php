<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Race;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RaceController extends Controller
{
    public function index(Request $request): Response
    {
        $visibility = in_array($request->query('visibility'), ['public', 'private'], true)
            ? $request->query('visibility')
            : null;

        return Inertia::render('Admin/Races/Index', [
            'filters' => ['visibility' => $visibility],
            'counts' => fn (): array => [
                'all' => Race::query()->count(),
                'public' => Race::query()->where('visibility', 'public')->count(),
                'private' => Race::query()->where('visibility', 'private')->count(),
            ],
            'races' => fn () => Race::query()
                ->with(['host:id,name', 'participants.user:id,name'])
                ->withCount('participants')
                ->when($visibility, fn ($query) => $query->where('visibility', $visibility))
                ->latest()
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Race $race): array => [
                    'id' => $race->id,
                    'code' => $race->code,
                    'visibility' => $race->visibility,
                    'status' => $race->status,
                    'host_id' => $race->host_user_id,
                    'host' => $race->host?->name,
                    'surah_number' => $race->surah_number,
                    'start_ayah' => $race->start_ayah,
                    'end_ayah' => $race->end_ayah,
                    'participants_count' => $race->participants_count,
                    'winner' => $race->participants->firstWhere('position', 1)?->user?->name,
                    'created_at' => $race->created_at->toIso8601String(),
                    'finished_at' => $race->finished_at?->toIso8601String(),
                ]),
        ]);
    }
}
