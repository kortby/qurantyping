<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\QuranText;
use Illuminate\Http\Client\RequestException;
use Throwable;

class QuranTextSeeder extends Seeder
{
    private const API_BASE_URL = 'https://quranapi.pages.dev/api';

    public function run(): void
    {
        if (QuranText::count() > 0) {
            $this->command->info('Quran texts table is not empty. Skipping seeding.');
            return;
        }

        try {
            $this->command->info('Step 1/2: Fetching list of all Surahs...');
            $surahs = Http::get(self::API_BASE_URL . '/surah.json')->json();
            $totalAyahs = array_sum(array_column($surahs, 'totalAyah'));
            $this->command->info("Found 114 Surahs with a total of {$totalAyahs} Ayahs.");

            $this->command->info('Step 2/2: Fetching and seeding all Ayahs. This will take several minutes...');
            $progressBar = $this->command->getOutput()->createProgressBar($totalAyahs);

            $allVerses = [];

            // *** THE FIX IS HERE ***
            // We now get the $index from the loop to determine the Surah number.
            foreach ($surahs as $index => $surah) {
                $surahNumber = $index + 1; // Surah 1 is at index 0, Surah 2 at index 1, etc.

                for ($ayahNum = 1; $ayahNum <= $surah['totalAyah']; $ayahNum++) {
                    $ayahData = Http::get(self::API_BASE_URL . "/{$surahNumber}/{$ayahNum}.json")->json();

                    if (isset($ayahData['arabic2']) && !empty($ayahData['arabic2'])) {
                        $allVerses[] = [
                            'surah_number' => $surahNumber, // Use the correct variable
                            'ayah_number' => $ayahNum,
                            'text_arabic_simple' => $ayahData['arabic2'],
                            'surah_name_arabic' => $surah['surahNameArabic'],
                            'surah_name_english' => $surah['surahName'],
                            'surah_name_translation' => $surah['surahNameTranslation'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    $progressBar->advance();
                }
            }

            // Insert all verses in chunks for performance
            foreach (array_chunk($allVerses, 500) as $chunk) {
                QuranText::insert($chunk);
            }

            $progressBar->finish();
            $this->command->info("\n✅ Database seeding completed successfully!");
        } catch (RequestException $e) {
            $this->command->error('API Request Failed: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->command->error('An unexpected error occurred: ' . $e->getMessage());
        }
    }
}
