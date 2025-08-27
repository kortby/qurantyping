<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('quran_text_id')->constrained('quran_texts')->onDelete('cascade');

            $table->string('mode'); // e.g., 'time', 'words', 'quote'
            $table->unsignedInteger('duration')->comment('in seconds or number of words');

            $table->unsignedInteger('wpm')->comment('Words Per Minute');
            $table->unsignedInteger('raw_wpm');
            $table->decimal('accuracy', 5, 2);

            $table->unsignedInteger('char_count');
            $table->unsignedInteger('correct_chars');
            $table->unsignedInteger('incorrect_chars');

            $table->timestamps();

            $table->index('user_id');
            $table->index('wpm');
            $table->index('accuracy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
