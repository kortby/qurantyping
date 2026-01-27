<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quran_texts', function (Blueprint $col) {
            $col->text('surah_arabic_ponctuation')->nullable()->after('text_arabic_simple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quran_texts', function (Blueprint $col) {
            $col->dropColumn('surah_arabic_ponctuation');
        });
    }
};
