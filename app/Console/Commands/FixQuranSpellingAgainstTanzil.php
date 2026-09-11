<?php

namespace App\Console\Commands;

use App\Models\QuranText;
use Illuminate\Console\Command;

class FixQuranSpellingAgainstTanzil extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran:fix-tanzil-spelling';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct the handful of spelling variants (vs. the Tanzil Quran text project) found in text_arabic_simple';

    /**
     * Every ayah of the seeded text (sourced from quranapi.pages.dev) was
     * diffed against Tanzil's "simple" text. These six are the only real
     * differences that survive normalizing known, already-accepted
     * conventions (hamza-on-alif, the Bismillah-in-ayah-1 convention) —
     * each is a well-known alternate orthography (alif vs. alif maqsura,
     * or a joined vs. split word), not a content error, but Tanzil is the
     * more widely-cited reference so we align to it. Re-run this any time
     * `quran:import-punctuation` or a fresh seed touches text_arabic_simple,
     * since neither preserves this correction on its own.
     *
     * @var list<array{0: int, 1: int, 2: string, 3: string}>
     */
    private array $fixes = [
        [2, 181, 'بعدما', 'بعد ما'],
        [5, 31, 'ويلتا', 'ويلتى'],
        [8, 6, 'بعدما', 'بعد ما'],
        [13, 37, 'بعدما', 'بعد ما'],
        [17, 32, 'الزنا', 'الزنى'],
        [39, 56, 'حسرتا', 'حسرتى'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach ($this->fixes as [$surah, $ayah, $from, $to]) {
            $row = QuranText::where('surah_number', $surah)->where('ayah_number', $ayah)->first();

            if (! $row) {
                $this->warn("Skipped {$surah}:{$ayah} — ayah not found.");

                continue;
            }

            if (substr_count($row->text_arabic_simple, $from) !== 1) {
                $this->info("Skipped {$surah}:{$ayah} — already fixed or text has changed.");

                continue;
            }

            $row->update([
                'text_arabic_simple' => preg_replace('/'.preg_quote($from, '/').'/u', $to, $row->text_arabic_simple, 1),
            ]);

            $this->info("Fixed {$surah}:{$ayah} ({$from} → {$to}).");
        }
    }
}
