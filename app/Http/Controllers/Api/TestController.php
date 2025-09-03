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
     * Fetch a new random Quran text for a typing test.
     */
    public function getNewTest(): JsonResponse
    {
        $text = QuranText::inRandomOrder()->first();

        if (!$text) {
            return response()->json(['message' => 'No Quranic texts found.'], 404);
        }

        // We can simplify the response for the frontend
        return response()->json([
            'id' => $text->id,
            'text' => $text->text_arabic_simple, // This is the key change
            'surah_number' => $text->surah_number,
            'ayah_number' => $text->ayah_number,
            'surah_name_arabic' => $text->surah_name_arabic,
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
