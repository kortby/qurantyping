<?php

namespace Database\Factories;

use App\Models\ClassGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassGroup>
 */
class ClassGroupFactory extends Factory
{
    protected $model = ClassGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(6)),
            'name' => $this->faker->words(2, true),
            'owner_user_id' => User::factory(),
        ];
    }
}
