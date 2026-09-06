<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestRequest;
use App\Models\QuranText;
use App\Models\Result;
use App\Models\Test;
use App\Services\QuranNavigator;
use App\Services\WeakLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(
        private readonly QuranNavigator $navigator,
        private readonly WeakLetterService $weakLetters,
    ) {}

    /**
     * Surah, juz' and page indexes for the passage selector.
     */
    public function getScopes(): JsonResponse
    {
        return response()->json([
            'juz' => $this->navigator->juzIndex(),
            'page_count' => $this->navigator->pageCount(),
        ]);
    }

    /**
     * Get a list of all Surahs for the selection dropdown.
     */
    public function getSurahs(): JsonResponse
    {
        // Fetch a distinct list of Surahs, ordered correctly.
        $surahs = QuranText::select('surah_number', 'surah_name_arabic', 'surah_name_english')
            ->selectRaw('MAX(ayah_number) as total_ayahs')
            ->groupBy('surah_number', 'surah_name_arabic', 'surah_name_english')
            ->orderBy('surah_number')
            ->get();

        return response()->json($surahs);
    }

    /**
     * Fetch and combine Quranic text for a specific range of Ayahs.
     */
    public function getTextForTest(Request $request): JsonResponse
    {
        if ($request->filled('after')) {
            // Resume / auto-advance: the passage after a given ayah.
            $request->validate(['after' => 'integer|min:1']);
            $location = $this->navigator->nextAfter((int) $request->query('after'));

            if (! $location) {
                return response()->json(['message' => 'You have reached the end of the Quran.'], 409);
            }

            ['surah_number' => $surahNumber, 'start_ayah' => $startAyah, 'end_ayah' => $endAyah] = $location;
        } elseif ($request->filled('scope') && $request->query('scope') !== 'surah') {
            // Structured navigation by juz' or mushaf page.
            $validated = $request->validate([
                'scope' => 'in:juz,page',
                'value' => 'required|integer|min:1',
            ]);
            $location = $this->navigator->resolve($validated['scope'], (int) $validated['value']);

            if (! $location) {
                return response()->json(['message' => 'No text found for that selection.'], 404);
            }

            ['surah_number' => $surahNumber, 'start_ayah' => $startAyah, 'end_ayah' => $endAyah] = $location;
        } elseif (! $request->has('surah_number')) {
            // No parameters: a random surah and 3 consecutive ayahs.
            $randomAyah = QuranText::inRandomOrder()->first();
            $surahNumber = $randomAyah->surah_number;

            $maxAyahInSurah = QuranText::where('surah_number', $surahNumber)->max('ayah_number');
            $startAyah = rand(1, max(1, $maxAyahInSurah - 2));
            $endAyah = min($maxAyahInSurah, $startAyah + 2);
        } else {
            $validated = $request->validate([
                'surah_number' => 'required|integer|min:1|max:114',
                'start_ayah' => 'required|integer|min:1',
                'end_ayah' => 'required|integer|min:1|gte:start_ayah',
            ]);
            $surahNumber = $validated['surah_number'];
            $startAyah = $validated['start_ayah'];
            $endAyah = $validated['end_ayah'];
        }

        return $this->buildPassageResponse((int) $surahNumber, (int) $startAyah, (int) $endAyah);
    }

    /**
     * A real Quran passage chosen for being dense in the user's weakest characters.
     */
    public function drillText(): JsonResponse
    {
        $weak = $this->weakLetters->weakChars(auth()->id());
        $location = $this->weakLetters->drillPassage($weak);

        if (! $location) {
            // Not enough signal yet: fall back to a random passage.
            $randomAyah = QuranText::inRandomOrder()->first();
            $surahNumber = $randomAyah->surah_number;
            $maxAyahInSurah = QuranText::where('surah_number', $surahNumber)->max('ayah_number');
            $startAyah = rand(1, max(1, $maxAyahInSurah - 2));
            $location = [
                'surah_number' => $surahNumber,
                'start_ayah' => $startAyah,
                'end_ayah' => min($maxAyahInSurah, $startAyah + 2),
            ];
        }

        return $this->buildPassageResponse(
            $location['surah_number'],
            $location['start_ayah'],
            $location['end_ayah'],
        );
    }

    /**
     * Assemble the combined-text JSON payload for a range of ayahs.
     */
    private function buildPassageResponse(int $surahNumber, int $startAyah, int $endAyah): JsonResponse
    {
        $ayahs = QuranText::where('surah_number', $surahNumber)
            ->whereBetween('ayah_number', [$startAyah, $endAyah])
            ->orderBy('ayah_number', 'asc')
            ->get();

        if ($ayahs->isEmpty()) {
            return response()->json(['message' => 'No Ayahs found for the selected range.'], 404);
        }

        // Combine the text of all fetched Ayahs into a single string with a decorative separator
        $combinedTextSimple = $ayahs->map(function ($ayah) {
            return trim($ayah->text_arabic_simple).' ۝'.$this->convertToArabicNumbers($ayah->ayah_number).' ';
        })->implode('');

        $combinedTextPunctuated = $ayahs->map(function ($ayah) {
            return trim($ayah->surah_arabic_ponctuation).' ۝'.$this->convertToArabicNumbers($ayah->ayah_number).' ';
        })->implode('');

        // Remove trailing space if any
        $combinedTextSimple = trim($combinedTextSimple);
        $combinedTextPunctuated = trim($combinedTextPunctuated);

        // The punctuated column may not be populated (needs `quran:import-punctuation`).
        // Without it the "text" is just the ۝ ayah markers — fall back to the simple text.
        if (! preg_match('/[\x{0621}-\x{064A}]/u', $combinedTextPunctuated)) {
            $combinedTextPunctuated = $combinedTextSimple;
        }

        // Enforce minimum word count for all selections (use simple text for word count)
        $wordCount = count(preg_split('/\s+/', trim($combinedTextSimple)));
        if ($wordCount < 10) {
            return response()->json([
                'message' => 'Selected text must contain at least 10 words.',
            ], 400);
        }

        return response()->json([
            'id' => $ayahs->first()->id,
            'last_quran_text_id' => $ayahs->last()->id,
            'text' => $combinedTextSimple, // Keep for backward compatibility
            'text_simple' => $combinedTextSimple,
            'text_punctuated' => $combinedTextPunctuated,
            'surah_name_arabic' => $ayahs->first()->surah_name_arabic,
            'surah_number' => $surahNumber,
            'start_ayah' => $startAyah,
            'end_ayah' => $endAyah,
        ]);
    }

    /**
     * Store a new typing test result.
     */
    public function store(StoreTestRequest $request): JsonResponse
    {
        // The request is already validated by StoreTestRequest
        $validatedData = $request->safe()->except(['char_stats', 'trace']);

        // Associate with the logged-in user, or leave as null for guests
        $validatedData['user_id'] = auth()->id();

        $test = Test::create($validatedData);

        $this->weakLetters->record($test->user_id, $request->safe()->collect('char_stats'));

        if ($test->user_id && $request->filled('trace')) {
            Result::create([
                'test_id' => $test->id,
                'history' => $request->validated('trace'),
            ]);
        }

        $newBadges = ($test->newBadges ?? collect())->map(fn ($b): array => [
            'name' => $b->name,
            'icon' => $b->icon,
            'description' => $b->description,
        ])->values();

        return response()->json(array_merge($test->toArray(), ['new_badges' => $newBadges]), 201);
    }

    /**
     * Convert numbers to Arabic-Indic digits.
     */
    private function convertToArabicNumbers($number): string
    {
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $numStr = (string) $number;
        $result = '';
        for ($i = 0; $i < strlen($numStr); $i++) {
            $result .= $arabicDigits[$numStr[$i]];
        }

        return $result;
    }
}
