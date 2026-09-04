<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ayah_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_text_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('learning'); // learning | review
            $table->float('ease')->default(2.5);
            $table->unsignedSmallInteger('interval_days')->default(0);
            $table->unsignedSmallInteger('reps')->default(0);
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->date('due_on');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('last_grade')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quran_text_id']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->unsignedTinyInteger('hifz_level')->nullable()->after('total_errors');
            $table->unsignedSmallInteger('peeks')->default(0)->after('hifz_level');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('hifz_daily_new')->default(5)->after('auto_advance');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('hifz_daily_new'));
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn(['hifz_level', 'peeks']));
        Schema::dropIfExists('user_ayah_progress');
    }
};
