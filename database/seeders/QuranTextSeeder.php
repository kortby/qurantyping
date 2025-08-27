<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuranTextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Set a higher timeout limit for the HTTP request, as the file is large.
        set_time_limit(300); // 5 minutes

        $url = 'https://api.alquran.cloud/v1/quran/quran-uthmani';

        try {
            $response = Http::timeout(300)->get($url);

            if ($response->successful()) {
                // The surahs are nested inside the 'data' object.
                $surahs = $response->json()['data']['surahs'];
                $quranTexts = [];

                // First loop: Iterate through each Surah
                foreach ($surahs as $surah) {
                    // Second loop: Iterate through each Ayah in the Surah
                    foreach ($surah['ayahs'] as $ayah) {
                        $quranTexts[] = [
                            'surah_number' => $surah['number'],
                            'ayah_number'  => $ayah['numberInSurah'],
                            'text'         => $ayah['text'],
                            'juz'          => $ayah['juz'],
                            'page'         => $ayah['page'],
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }
                }

                // Truncate the table before seeding to avoid duplicates on re-run.
                DB::table('quran_texts')->truncate();

                // Insert data in chunks for better memory management.
                foreach (array_chunk($quranTexts, 500) as $chunk) {
                    DB::table('quran_texts')->insert($chunk);
                }

                $this->command->info('Quran text has been seeded successfully!');
            } else {
                // Log the error if the API call fails.
                $this->command->error('Failed to fetch data from the API.');
                Log::error('Quran API fetch failed: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->command->error('An error occurred: ' . $e->getMessage());
            Log::error('QuranSeeder error: ' . $e->getMessage());
        }
    }
}
