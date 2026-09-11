<?php

namespace App\Http\Controllers;

use App\Models\QuranText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SurahController extends Controller
{
    /**
     * Browsable index of every surah, linking to its own landing page.
     */
    public function index(): Response
    {
        View::share('meta', [
            'title' => 'Browse Every Surah — Type & Memorize Online | QuranTyping',
            'description' => "Pick any surah of the Qur'an to practise typing in Arabic or memorise with Hifz mode. Free, no signup required.",
        ]);

        return Inertia::render('Surah/Index', [
            'surahs' => static::all()->values(),
        ]);
    }

    /**
     * A single surah's landing page, targeted at its own name/keyword.
     */
    public function show(string $slug): Response
    {
        $surah = static::all()->firstWhere('slug', $slug);

        abort_if(! $surah, 404);

        View::share('meta', [
            'title' => "Surah {$surah['name_english']} — Type & Memorize Online | QuranTyping",
            'description' => "Practise typing Surah {$surah['name_english']} ({$surah['name_arabic']}), {$surah['ayah_count']} verses, in Arabic with live accuracy feedback and full harakat. Free, no signup needed.",
        ]);

        View::share('faq', [
            [
                'q' => "How many verses does Surah {$surah['name_english']} have?",
                'a' => "Surah {$surah['name_english']} ({$surah['name_arabic']}) has {$surah['ayah_count']} verses.",
            ],
            [
                'q' => "Is it free to type or memorise Surah {$surah['name_english']}?",
                'a' => 'Yes. Every feature is free, and you can start typing immediately without creating an account.',
            ],
        ]);

        return Inertia::render('Surah/Show', [
            'surah' => $surah,
        ]);
    }

    /**
     * Every surah with its number, names, verse count, and URL slug.
     * Cached — this only changes if the seeded Qur'an text changes.
     *
     * @return Collection<int, array{surah_number:int, name_english:string, name_arabic:string, ayah_count:int, slug:string}>
     */
    public static function all(): Collection
    {
        // Cached as a plain array, not a Collection — some cache stores
        // mishandle unserializing Collection objects back into memory.
        return collect(Cache::remember('surah.landing_pages', now()->addDay(), function (): array {
            return QuranText::query()
                ->select('surah_number', 'surah_name_english', 'surah_name_arabic')
                ->selectRaw('MAX(ayah_number) as total_ayahs')
                ->groupBy('surah_number', 'surah_name_english', 'surah_name_arabic')
                ->orderBy('surah_number')
                ->get()
                ->map(fn ($row): array => [
                    'surah_number' => (int) $row->surah_number,
                    'name_english' => $row->surah_name_english,
                    'name_arabic' => $row->surah_name_arabic,
                    'ayah_count' => (int) $row->total_ayahs,
                    'slug' => Str::slug($row->surah_name_english),
                ])
                ->values()
                ->all();
        }));
    }
}
