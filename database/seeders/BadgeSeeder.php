<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = collect(config('badges.list'))->map(fn (array $b): array => [
            'slug' => $b['slug'],
            'name' => $b['name'],
            'description' => $b['description'],
            'icon' => $b['icon'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Badge::upsert($rows, ['slug'], ['name', 'description', 'icon', 'updated_at']);
    }
}
