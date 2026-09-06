<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLetterStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLetterStat>
 */
class UserLetterStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attempts = $this->faker->numberBetween(10, 200);

        return [
            'user_id' => User::factory(),
            'character' => $this->faker->randomElement(['ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'ه', 'و', 'ي']),
            'attempts' => $attempts,
            'misses' => $this->faker->numberBetween(0, $attempts),
        ];
    }
}
