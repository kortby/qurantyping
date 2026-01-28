<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Test>
 */
class TestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quranText = \App\Models\QuranText::inRandomOrder()->first() ?? \App\Models\QuranText::factory()->create();

        $maxAyah = \App\Models\QuranText::where('surah_number', $quranText->surah_number)->max('ayah_number');

        $startAyah = $this->faker->numberBetween(1, $maxAyah);
        // Ensure end_ayah is at least start_ayah and at most maxAyah, with a reasonable range (1-5 ayahs)
        $endAyah = $this->faker->numberBetween($startAyah, min($startAyah + 2, $maxAyah));

        return [
            'user_id' => \App\Models\User::inRandomOrder()->first()?->id ?? \App\Models\User::factory(),
            'quran_text_id' => $quranText->id,
            'mode' => $this->faker->randomElement(['time', 'words', 'quote']),
            'duration' => $this->faker->numberBetween(15, 60),
            'wpm' => $this->faker->numberBetween(20, 100),
            'raw_wpm' => $this->faker->numberBetween(25, 110),
            'accuracy' => $this->faker->randomFloat(2, 80, 100),
            'char_count' => $this->faker->numberBetween(100, 500),
            'correct_chars' => $this->faker->numberBetween(90, 480),
            'incorrect_chars' => $this->faker->numberBetween(0, 20),
            'start_ayah' => $startAyah,
            'end_ayah' => $endAyah,
            'total_errors' => $this->faker->numberBetween(0, 10),
        ];
    }
}
