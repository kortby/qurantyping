<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahCompletion;
use Illuminate\Support\Collection;

class CertificateService
{
    /**
     * Fold a completed test into the user's per-ayah accuracy record, then
     * issue a certificate if the surah is now fully covered at the bar.
     */
    public function recordTest(Test $test): void
    {
        if (! $test->user_id || ! $test->start_ayah || ! $test->end_ayah) {
            return;
        }

        $surah = QuranText::whereKey($test->quran_text_id)->value('surah_number');

        if (! $surah) {
            return;
        }

        $ayahIds = QuranText::where('surah_number', $surah)
            ->whereBetween('ayah_number', [$test->start_ayah, $test->end_ayah])
            ->pluck('id');

        foreach ($ayahIds as $id) {
            $row = UserAyahCompletion::firstOrNew([
                'user_id' => $test->user_id,
                'quran_text_id' => $id,
            ]);

            $row->best_accuracy = max((float) $row->best_accuracy, (float) $test->accuracy);
            $row->attempts = (int) $row->attempts + 1;
            $row->save();
        }

        $this->checkSurah($test->user, $surah);
    }

    /**
     * Issue a certificate for a surah if every ayah is at or above the bar
     * and one hasn't been issued already.
     */
    public function checkSurah(User $user, int $surah): ?Certificate
    {
        if (Certificate::where('user_id', $user->id)->where('surah_number', $surah)->exists()) {
            return null;
        }

        $bar = (float) config('hifz.certificate_accuracy', 95);
        $ayahIds = QuranText::where('surah_number', $surah)->pluck('id');

        if ($ayahIds->isEmpty()) {
            return null;
        }

        $completions = UserAyahCompletion::where('user_id', $user->id)
            ->whereIn('quran_text_id', $ayahIds)
            ->get(['quran_text_id', 'best_accuracy']);

        if ($completions->count() < $ayahIds->count()) {
            return null;
        }

        if ($completions->contains(fn ($c) => (float) $c->best_accuracy < $bar)) {
            return null;
        }

        $names = QuranText::where('surah_number', $surah)->first(['surah_name_english', 'surah_name_arabic']);

        return Certificate::create([
            'user_id' => $user->id,
            'surah_number' => $surah,
            'surah_name_english' => $names->surah_name_english,
            'surah_name_arabic' => $names->surah_name_arabic,
            'ayah_count' => $ayahIds->count(),
            'accuracy' => round((float) $completions->min('best_accuracy'), 2),
            'issued_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, Certificate>
     */
    public function forUser(User $user): Collection
    {
        return Certificate::where('user_id', $user->id)
            ->orderByDesc('issued_at')
            ->get();
    }
}
