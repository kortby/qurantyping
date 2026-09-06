<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_letter_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('character', 8);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('misses')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'character']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_letter_stats');
    }
};
