<?php

use App\Services\StreakService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('current_streak')->default(0)->after('error_sound');
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
            $table->date('last_practiced_on')->nullable()->after('longest_streak');
            $table->date('streak_grace_used_on')->nullable()->after('last_practiced_on');
            $table->unsignedInteger('daily_goal_chars')->default(500)->after('streak_grace_used_on');
        });

        // Compute streaks for existing users from their backfilled activity.
        DB::table('daily_activity')
            ->select('user_id')
            ->groupBy('user_id')
            ->pluck('user_id')
            ->each(function ($userId): void {
                $dates = DB::table('daily_activity')
                    ->where('user_id', $userId)
                    ->orderBy('date')
                    ->pluck('date')
                    ->all();

                $streak = StreakService::computeFromDates($dates);

                DB::table('users')->where('id', $userId)->update([
                    'current_streak' => $streak['current'],
                    'longest_streak' => $streak['longest'],
                    'last_practiced_on' => $streak['last_practiced_on'],
                    'streak_grace_used_on' => $streak['grace_used_on'],
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'current_streak',
                'longest_streak',
                'last_practiced_on',
                'streak_grace_used_on',
                'daily_goal_chars',
            ]);
        });
    }
};
