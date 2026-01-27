<?php

namespace App\Console\Commands;

use App\Models\QuranText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportQuranPunctuation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran:import-punctuation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Arabic punctuation (arabic1) from quranapi.pages.dev';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Quran punctuation import...');

        for ($surahNumber = 1; $surahNumber <= 114; $surahNumber++) {
            $this->info("Fetching Surah {$surahNumber}...");

            try {
                $response = Http::get("https://quranapi.pages.dev/api/{$surahNumber}.json");

                if ($response->successful()) {
                    $data = $response->json();
                    $arabic1 = $data['arabic1'] ?? [];

                    foreach ($arabic1 as $index => $text) {
                        $ayahNumber = $index + 1;

                        // Update all occurrences of this ayah in the database
                        QuranText::where('surah_number', $surahNumber)
                            ->where('ayah_number', $ayahNumber)
                            ->update(['surah_arabic_ponctuation' => $text]);
                    }

                    $this->info("Surah {$surahNumber} updated successfully.");
                } else {
                    $this->error("Failed to fetch Surah {$surahNumber}. Status: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("Error fetching Surah {$surahNumber}: " . $e->getMessage());
            }

            // Wait 2 seconds before next request as requested
            if ($surahNumber < 114) {
                $this->info('Waiting 2 seconds...');
                sleep(2);
            }
        }

        $this->info('Import completed!');
    }
}
