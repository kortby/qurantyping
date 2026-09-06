<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;
use App\Models\UserAyahCompletion;
use App\Services\CertificateService;
use Illuminate\Console\Command;

class RebuildAyahProgress extends Command
{
    protected $signature = 'progress:rebuild {--user= : Only this user id} {--no-certs : Skip re-issuing certificates}';

    protected $description = 'Rebuild user_ayah_completions from every historical test (idempotent). Fixes the Qur\'an map for pre-2026-09-04 activity.';

    public function handle(CertificateService $certificates): int
    {
        // id -> surah, and [surah][ayah] -> id, loaded once (6236 rows).
        $texts = QuranText::query()->get(['id', 'surah_number', 'ayah_number']);
        $surahOf = $texts->pluck('surah_number', 'id');
        $ayahId = $texts->groupBy('surah_number')->map(fn ($g) => $g->pluck('id', 'ayah_number'));

        $userFilter = $this->option('user');
        $users = 0;
        $rows = 0;

        Test::query()
            ->whereNotNull('user_id')
            ->whereNotNull('start_ayah')
            ->whereNotNull('end_ayah')
            ->when($userFilter, fn ($q) => $q->where('user_id', $userFilter))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function ($userId) use ($surahOf, $ayahId, $certificates, &$users, &$rows): void {
                /** @var array<int, array{acc: float, n: int}> $agg */
                $agg = [];

                Test::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('start_ayah')
                    ->whereNotNull('end_ayah')
                    ->select('id', 'quran_text_id', 'start_ayah', 'end_ayah', 'accuracy')
                    ->chunkById(1000, function ($tests) use ($surahOf, $ayahId, &$agg): void {
                        foreach ($tests as $t) {
                            $surah = $surahOf[$t->quran_text_id] ?? null;

                            if (! $surah || ! isset($ayahId[$surah])) {
                                continue;
                            }

                            for ($a = (int) $t->start_ayah; $a <= (int) $t->end_ayah; $a++) {
                                $id = $ayahId[$surah][$a] ?? null;

                                if (! $id) {
                                    continue;
                                }

                                $agg[$id]['acc'] = max($agg[$id]['acc'] ?? 0.0, (float) $t->accuracy);
                                $agg[$id]['n'] = ($agg[$id]['n'] ?? 0) + 1;
                            }
                        }
                    });

                if ($agg === []) {
                    return;
                }

                $now = now();
                $payload = [];
                foreach ($agg as $id => $a) {
                    $payload[] = [
                        'user_id' => $userId,
                        'quran_text_id' => $id,
                        'best_accuracy' => round($a['acc'], 2),
                        'attempts' => $a['n'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($payload, 500) as $slice) {
                    UserAyahCompletion::upsert($slice, ['user_id', 'quran_text_id'], ['best_accuracy', 'attempts', 'updated_at']);
                }

                $rows += count($payload);
                $users++;

                if (! $this->option('no-certs')) {
                    $user = User::find($userId);
                    if ($user) {
                        QuranText::query()
                            ->whereIn('id', array_keys($agg))
                            ->distinct()
                            ->pluck('surah_number')
                            ->each(fn ($s) => $certificates->checkSurah($user, (int) $s));
                    }
                }
            });

        $this->info("Rebuilt {$rows} completion row(s) for {$users} user(s).");
        $this->line('Certificates: '.Certificate::count().' total.');

        return self::SUCCESS;
    }
}
