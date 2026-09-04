<?php

namespace Database\Seeders;

use App\Models\QuranText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuranDivisionsSeeder extends Seeder
{
    /**
     * Backfill juz / hizb quarter / mushaf page onto every ayah from the
     * bundled Madani-mushaf division map. Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $path = database_path('data/quran-divisions.json');

        if (! is_file($path)) {
            $this->command?->error("Missing {$path}");

            return;
        }

        if (QuranText::count() === 0) {
            $this->command?->warn('quran_texts is empty — run QuranTextSeeder first.');

            return;
        }

        /** @var array<string, array{0:int,1:int,2:int}> $divisions */
        $divisions = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $updated = 0;

        DB::transaction(function () use ($divisions, &$updated): void {
            foreach (array_chunk($divisions, 500, true) as $chunk) {
                foreach ($chunk as $ref => [$juz, $hizb, $page]) {
                    [$surah, $ayah] = array_map('intval', explode(':', $ref));

                    $updated += QuranText::query()
                        ->where('surah_number', $surah)
                        ->where('ayah_number', $ayah)
                        ->update(['juz' => $juz, 'hizb_quarter' => $hizb, 'page' => $page]);
                }
            }
        });

        $this->command?->info("Division map applied to {$updated} ayahs.");
    }
}
