<?php

namespace App\Http\Controllers;

use App\Http\Requests\Race\FinishRaceRequest;
use App\Models\QuranText;
use App\Models\Race;
use App\Services\RaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RaceController extends Controller
{
    public function __construct(private readonly RaceService $races) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Races/Index', [
            'recent' => fn (): array => $request->user()->raceParticipations()
                ->whereNotNull('finished_at')
                ->with('race:id,code,visibility,surah_number,start_ayah,end_ayah')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($p): array => [
                    'position' => $p->position,
                    'wpm' => $p->wpm,
                    'accuracy' => (float) $p->accuracy,
                    'surah_number' => $p->race?->surah_number,
                    'start_ayah' => $p->race?->start_ayah,
                    'end_ayah' => $p->race?->end_ayah,
                    'finished_at' => $p->finished_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function quick(Request $request): RedirectResponse
    {
        $race = $this->races->quickMatch($request->user());

        return redirect()->route('races.show', $race->channelKey());
    }

    public function store(Request $request): RedirectResponse
    {
        $race = $this->races->createPrivate($request->user());

        return redirect()->route('races.show', $race->channelKey());
    }

    public function join(Request $request, string $key): RedirectResponse
    {
        $race = $this->races->resolve($key);

        abort_if(! $race, 404);

        $this->races->join($race, $request->user());

        return redirect()->route('races.show', $race->channelKey());
    }

    public function show(Request $request, string $key): Response|RedirectResponse
    {
        $race = $this->races->resolve($key);

        abort_if(! $race, 404);

        $isParticipant = $race->participants()->where('user_id', $request->user()->id)->exists();

        if (! $isParticipant && $race->isJoinable()) {
            $this->races->join($race, $request->user());
            $isParticipant = true;
        }

        abort_if(! $isParticipant, 403);

        $race->load('participants.user:id,name');
        $revealText = $race->status !== 'lobby' || $race->host_user_id === $request->user()->id;

        $isHostLobby = $race->status === 'lobby'
            && $race->visibility === 'private'
            && $race->host_user_id === $request->user()->id;

        return Inertia::render('Races/Room', [
            'race' => [
                'key' => $race->channelKey(),
                'code' => $race->code,
                'visibility' => $race->visibility,
                'status' => $race->status,
                'is_host' => $race->host_user_id === $request->user()->id,
                'starts_at' => $race->starts_at?->toIso8601String(),
                'surah_number' => $race->surah_number,
                'start_ayah' => $race->start_ayah,
                'end_ayah' => $race->end_ayah,
                'char_target' => $race->char_target,
                'capacity' => $race->seatLimit(),
                'scope_surah' => $race->scope_surah,
                'text' => $revealText ? $race->text : null,
                'min_to_start' => Race::MIN_TO_START,
            ],
            'limits' => [
                'min_chars' => RaceService::MIN_CHARS,
                'max_chars' => RaceService::MAX_CHARS,
                'min_capacity' => RaceService::MIN_CAPACITY,
                'max_capacity' => RaceService::MAX_CAPACITY,
            ],
            'surahs' => $isHostLobby
                ? fn () => QuranText::query()
                    ->select('surah_number', 'surah_name_english')
                    ->distinct()
                    ->orderBy('surah_number')
                    ->get()
                : [],
            'participants' => $race->participants
                ->sortBy(fn ($p) => [$p->position ?? 99, $p->joined_at->timestamp])
                ->values()
                ->map(fn ($p): array => [
                    'id' => $p->user_id,
                    'name' => $p->user?->name,
                    'finished' => (bool) $p->finished_at,
                    'position' => $p->position,
                    'wpm' => $p->wpm,
                    'accuracy' => $p->accuracy !== null ? (float) $p->accuracy : null,
                ])
                ->all(),
            'me' => $request->user()->id,
        ]);
    }

    public function start(Request $request, string $key): RedirectResponse
    {
        $race = $this->races->resolve($key);

        abort_if(! $race, 404);
        abort_if($race->host_user_id !== $request->user()->id, 403);
        abort_if($race->visibility !== 'private', 403);
        abort_if($race->participants()->count() < 1, 422);

        $validated = $request->validate([
            'char_target' => ['sometimes', 'integer', 'min:'.RaceService::MIN_CHARS, 'max:'.RaceService::MAX_CHARS],
            'capacity' => ['sometimes', 'integer', 'min:'.RaceService::MIN_CAPACITY, 'max:'.RaceService::MAX_CAPACITY],
            'scope_surah' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:114'],
        ]);

        $this->races->startPrivate($race, $validated);

        return back();
    }

    public function finish(FinishRaceRequest $request, string $key): JsonResponse
    {
        $race = $this->races->resolve($key);

        abort_if(! $race, 404);

        $participant = $this->races->finishParticipant(
            $race,
            $request->user(),
            (int) $request->integer('chars'),
            (int) $request->integer('correct_chars'),
        );

        return response()->json([
            'wpm' => $participant->wpm,
            'accuracy' => (float) $participant->accuracy,
            'position' => $participant->position,
        ]);
    }

    public function progress(Request $request, string $key): JsonResponse
    {
        $race = $this->races->resolve($key);

        if ($race) {
            $this->races->relayProgress(
                $race,
                $request->user(),
                (float) $request->float('pct'),
                (int) $request->integer('wpm'),
            );
        }

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, string $key): RedirectResponse
    {
        $race = $this->races->resolve($key);

        if ($race) {
            $this->races->leave($race, $request->user());
        }

        return redirect()->route('races.index');
    }
}
