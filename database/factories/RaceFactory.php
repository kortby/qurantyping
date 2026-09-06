<?php

namespace Database\Factories;

use App\Models\QuranText;
use App\Models\Race;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Race>
 */
class RaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ayah = QuranText::query()->inRandomOrder()->first();
        $text = 'كلمة كلمة كلمة كلمة كلمة كلمة كلمة كلمة كلمة كلمة كلمة كلمة';

        return [
            'code' => null,
            'visibility' => 'public',
            'status' => 'lobby',
            'host_user_id' => null,
            'quran_text_id' => $ayah?->id ?? 1,
            'surah_number' => $ayah?->surah_number ?? 1,
            'start_ayah' => $ayah?->ayah_number ?? 1,
            'end_ayah' => ($ayah?->ayah_number ?? 1) + 2,
            'text' => $text,
            'char_target' => mb_strlen($text),
            'starts_at' => null,
            'finished_at' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (): array => [
            'visibility' => 'private',
            'code' => Str::upper(Str::random(6)),
            'host_user_id' => User::factory(),
        ]);
    }

    public function countingDown(): static
    {
        return $this->state(fn (): array => [
            'status' => 'countdown',
            'starts_at' => now()->addSeconds(Race::COUNTDOWN_SECONDS),
        ]);
    }

    public function racing(): static
    {
        return $this->state(fn (): array => [
            'status' => 'racing',
            'starts_at' => now()->subSeconds(3),
        ]);
    }
}
