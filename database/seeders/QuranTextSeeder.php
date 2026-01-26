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
        try {
            $this->command->info('Step 1/2: Fetching list of all Surahs...');

            // Retry fetching the surah list if the network is flaky
            $response = Http::retry(3, 1000)->get(self::API_BASE_URL . '/surah.json');

            if (!$response->successful()) {
                $this->command->error('Failed to fetch surah list from API.');
                return;
            }

            $surahs = $response->json();
            $totalAyahsInApi = array_sum(array_column($surahs, 'totalAyah'));

            $existingCount = QuranText::count();

            // If we already have everything, stop.
            if ($existingCount >= $totalAyahsInApi) {
                $this->command->info("Database already contains all {$totalAyahsInApi} Ayahs. Skipping.");
                return;
            }

            $this->command->info("Found 114 Surahs with a total of {$totalAyahsInApi} Ayahs.");
            if ($existingCount > 0) {
                $this->command->info("Current database has {$existingCount} Ayahs. Resuming from where we stopped...");
            } else {
                $this->command->info("Starting fresh seeding. This will take several minutes...");
            }

            $progressBar = $this->command->getOutput()->createProgressBar($totalAyahsInApi);
            $progressBar->setProgress($existingCount);

            foreach ($surahs as $index => $surah) {
                $surahNumber = $index + 1;
                $batchToInsert = [];

                for ($ayahNum = 1; $ayahNum <= $surah['totalAyah']; $ayahNum++) {
                    // RESUME LOGIC: Check if this specific ayah is already in the DB
                    $exists = QuranText::where('surah_number', $surahNumber)
                        ->where('ayah_number', $ayahNum)
                        ->exists();

                    if ($exists) {
                        // Already in DB, just move the progress bar
                        continue;
                    }

                    // ROBUST FETCHING: Retry up to 5 times with a 10s timeout per try
                    $ayahResponse = Http::timeout(15)
                        ->retry(5, 2000)
                        ->get(self::API_BASE_URL . "/{$surahNumber}/{$ayahNum}.json");

                    if ($ayahResponse->successful()) {
                        $ayahData = $ayahResponse->json();

                        if (isset($ayahData['arabic2']) && !empty($ayahData['arabic2'])) {
                            $batchToInsert[] = [
                                'surah_number' => $surahNumber,
                                'ayah_number' => $ayahNum,
                                'text_arabic_simple' => $ayahData['arabic2'],
                                'surah_name_arabic' => $surah['surahNameArabic'],
                                'surah_name_english' => $surah['surahName'],
                                'surah_name_translation' => $surah['surahNameTranslation'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    } else {
                        $this->command->warn("\nFailed to fetch Ayah {$ayahNum} of Surah {$surahNumber} after retries. Skipping for now.");
                    }

                    $progressBar->advance();

                    // SAVE PROGRESS: Insert in small batches to frequent the disk and avoid memory bloat
                    if (count($batchToInsert) >= 20) {
                        QuranText::insert($batchToInsert);
                        $batchToInsert = [];
                        // Minor sleep to be kind to the API and avoid triggering WAFs
                        usleep(50000); // 0.05 seconds
                    }
                }

                // Insert remaining for the current Surah
                if (!empty($batchToInsert)) {
                    QuranText::insert($batchToInsert);
                }
            }

            $progressBar->finish();
            $this->command->info("\n✅ Database seeding successfully completed!");

        } catch (Throwable $e) {
            $this->command->error("\nAn error occurred: " . $e->getMessage());
            $this->command->info("DON'T WORRY: You can run 'php artisan db:seed' again to RESUME from where it failed.");
        }
    }
}
