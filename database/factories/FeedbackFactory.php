<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'message' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['bug', 'suggestion', 'other']),
            'handled_at' => null,
        ];
    }

    public function handled(): static
    {
        return $this->state(fn (): array => ['handled_at' => now()]);
    }
}
