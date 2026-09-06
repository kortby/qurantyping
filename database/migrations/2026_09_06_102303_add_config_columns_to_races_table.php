<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->boolean('tashkeel')->default(false)->after('char_target');
            $table->unsignedTinyInteger('capacity')->default(5)->after('tashkeel');
            $table->unsignedSmallInteger('scope_surah')->nullable()->after('capacity');
        });

        // A private room is created before its passage is chosen, so the passage
        // columns must allow nulls until the host starts it.
        Schema::table('races', function (Blueprint $table) {
            $table->dropForeign(['quran_text_id']);
        });

        Schema::table('races', function (Blueprint $table) {
            $table->unsignedBigInteger('quran_text_id')->nullable()->change();
            $table->unsignedSmallInteger('surah_number')->nullable()->change();
            $table->unsignedSmallInteger('start_ayah')->nullable()->change();
            $table->unsignedSmallInteger('end_ayah')->nullable()->change();
            $table->text('text')->nullable()->change();
            $table->unsignedInteger('char_target')->nullable()->change();
        });

        Schema::table('races', function (Blueprint $table) {
            $table->foreign('quran_text_id')->references('id')->on('quran_texts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['tashkeel', 'capacity', 'scope_surah']);
        });
    }
};
