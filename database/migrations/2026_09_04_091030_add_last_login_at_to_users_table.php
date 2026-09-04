<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('last_practiced_on');
        });

        // Best-effort backfill from active sessions (database session driver).
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->whereNotNull('user_id')
                ->selectRaw('user_id, MAX(last_activity) as la')
                ->groupBy('user_id')
                ->get()
                ->each(fn ($row) => DB::table('users')
                    ->where('id', $row->user_id)
                    ->update(['last_login_at' => CarbonImmutable::createFromTimestamp($row->la)]));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_login_at'));
    }
};
