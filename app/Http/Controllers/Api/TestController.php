<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestRequest;
use Illuminate\Http\Request;
use App\Models\QuranText;
use App\Models\Test;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Get a list of all Surahs for the selection dropdown.
     */
    public function getSurahs(): JsonResponse
    {
        // Fetch a distinct list of Surahs, ordered correctly.
        $surahs = QuranText::select('surah_number', 'surah_name_arabic', 'surah_name_english')
            ->distinct()
            ->orderBy('surah_number')
            ->get();

        return response()->json($surahs);
    }

    /**
     * Fetch and combine Quranic text for a specific range of Ayahs.
     */
    public function getTextForTest(Request $request): JsonResponse
    {
        // Validate the incoming request parameters
        $validated = $request->validate([
            'surah_number' => 'required|integer|min:1|max:114',
            'start_ayah' => 'required|integer|min:1',
            'end_ayah' => 'required|integer|min:1|gte:start_ayah',
        ]);

        // Query the database for the requested range of Ayahs
        $ayahs = QuranText::where('surah_number', $validated['surah_number'])
            ->whereBetween('ayah_number', [$validated['start_ayah'], $validated['end_ayah']])
            ->orderBy('ayah_number', 'asc')
            ->get();

        if ($ayahs->isEmpty()) {
            return response()->json(['message' => 'No Ayahs found for the selected range.'], 404);
        }

        // Combine the text of all fetched Ayahs into a single string
        $combinedText = $ayahs->pluck('text_arabic_simple')->implode(' ');

        return response()->json([
            'id' => $ayahs->first()->id,
            'text' => $combinedText,
            'surah_name_arabic' => $ayahs->first()->surah_name_arabic,
            'surah_number' => $validated['surah_number'],
            'start_ayah' => $validated['start_ayah'],
            'end_ayah' => $validated['end_ayah'],
        ]);
    }

    /**
     * Store a new typing test result.
     */
    public function store(StoreTestRequest $request): JsonResponse
    {
        // The request is already validated by StoreTestRequest
        $validatedData = $request->validated();

        // Associate with the logged-in user, or leave as null for guests
        $validatedData['user_id'] = auth()->id();

        $test = Test::create($validatedData);

        return response()->json($test, 201); // 201 Created status
    }
}
