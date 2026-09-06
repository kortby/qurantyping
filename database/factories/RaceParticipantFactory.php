<?php

namespace Database\Factories;

use App\Models\Race;
use App\Models\RaceParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RaceParticipant>
 */
class RaceParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'race_id' => Race::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'finished_at' => null,
            'wpm' => null,
            'accuracy' => null,
            'chars' => null,
            'position' => null,
            'test_id' => null,
        ];
    }

    public function finished(int $position = 1): static
    {
        return $this->state(fn (): array => [
            'finished_at' => now(),
            'wpm' => $this->faker->numberBetween(30, 90),
            'accuracy' => $this->faker->randomFloat(2, 85, 100),
            'chars' => $this->faker->numberBetween(120, 300),
            'position' => $position,
        ]);
    }
}
