<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->char('code', 6)->nullable()->unique();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['lobby', 'countdown', 'racing', 'finished', 'abandoned'])->default('lobby');
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quran_text_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('surah_number');
            $table->unsignedSmallInteger('start_ayah');
            $table->unsignedSmallInteger('end_ayah');
            $table->text('text');
            $table->unsignedInteger('char_target');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'status', 'created_at']);
        });

        Schema::create('race_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('wpm')->nullable();
            $table->decimal('accuracy', 5, 2)->nullable();
            $table->unsignedInteger('chars')->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->foreignId('test_id')->nullable()->constrained('tests')->nullOnDelete();
            $table->timestamps();

            $table->unique(['race_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_participants');
        Schema::dropIfExists('races');
    }
};
