<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('tests_count')->default(0);
            $table->unsignedInteger('chars')->default(0);
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });

        // Backfill one row per user per day from existing tests. "Day" is the
        // date in the application timezone (config('app.timezone')).
        $rows = DB::table('tests')
            ->selectRaw('user_id, DATE(created_at) as date, COUNT(*) as tests_count, SUM(char_count) as chars, SUM(duration) as seconds')
            ->whereNotNull('user_id')
            ->groupBy('user_id', DB::raw('DATE(created_at)'))
            ->get();

        $now = now();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('daily_activity')->insert($chunk->map(fn ($r): array => [
                'user_id' => $r->user_id,
                'date' => $r->date,
                'tests_count' => $r->tests_count,
                'chars' => (int) $r->chars,
                'seconds' => (int) $r->seconds,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activity');
    }
};
