<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_texts', function (Blueprint $table) {
            // Populated by QuranDivisionsSeeder from database/data/quran-divisions.json.
            $table->unsignedTinyInteger('juz')->nullable()->after('ayah_number');
            $table->unsignedTinyInteger('hizb_quarter')->nullable()->after('juz');
            $table->unsignedSmallInteger('page')->nullable()->after('hizb_quarter');

            $table->index('juz');
            $table->index('page');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_quran_text_id')->nullable()->after('daily_goal_chars')
                ->constrained('quran_texts')->nullOnDelete();
            $table->boolean('auto_advance')->default(false)->after('last_quran_text_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_quran_text_id');
            $table->dropColumn('auto_advance');
        });

        Schema::table('quran_texts', function (Blueprint $table) {
            $table->dropIndex(['juz']);
            $table->dropIndex(['page']);
            $table->dropColumn(['juz', 'hizb_quarter', 'page']);
        });
    }
};
