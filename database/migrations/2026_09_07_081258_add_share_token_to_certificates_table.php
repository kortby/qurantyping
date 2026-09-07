<?php

use App\Models\Certificate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('share_token', 12)->nullable()->unique()->after('id');
        });

        Certificate::query()->whereNull('share_token')->get()->each(function (Certificate $certificate): void {
            $certificate->forceFill(['share_token' => Str::lower(Str::random(10))])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
