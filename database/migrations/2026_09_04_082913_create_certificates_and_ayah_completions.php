<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ayah_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_text_id')->constrained()->cascadeOnDelete();
            $table->decimal('best_accuracy', 5, 2)->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'quran_text_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('surah_number');
            $table->string('surah_name_english');
            $table->string('surah_name_arabic');
            $table->unsignedSmallInteger('ayah_count');
            $table->decimal('accuracy', 5, 2);
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['user_id', 'surah_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('user_ayah_completions');
    }
};
