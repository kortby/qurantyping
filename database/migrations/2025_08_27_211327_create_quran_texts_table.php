<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quran_texts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('surah_number');
            $table->unsignedSmallInteger('ayah_number');
            
            // This will store the simple, clean Arabic text for typing
            $table->text('text_arabic_simple');

            // Store Surah details for a richer UI
            $table->string('surah_name_arabic');
            $table->string('surah_name_english');
            $table->string('surah_name_translation');
            
            $table->timestamps();
            $table->unique(['surah_number', 'ayah_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_texts');
    }
};
