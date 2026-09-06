<?php

namespace App\Http\Controllers;

use App\Services\QuranMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuranMapController extends Controller
{
    public function __construct(private readonly QuranMapService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Map/Index', [
            'map' => fn (): array => $this->service->overview($request->user()),
        ]);
    }

    public function surah(Request $request, int $surah): JsonResponse
    {
        abort_unless($surah >= 1 && $surah <= 114, 404);

        return response()->json($this->service->surahDetail($request->user(), $surah));
    }
}
