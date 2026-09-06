<?php

namespace App\Services;

use App\Events\Race\RaceFinished;
use App\Events\Race\RaceLobbyUpdated;
use App\Events\Race\RaceParticipantFinished;
use App\Events\Race\RaceProgress;
use App\Events\Race\RaceStarted;
use App\Events\Race\RaceStarting;
use App\Jobs\StartRaceJob;
use App\Models\QuranText;
use App\Models\Race;
use App\Models\RaceParticipant;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RaceService
{
    public const MAX_PLAUSIBLE_WPM = 250;

    public const MIN_CHARS = 100;

    public const MAX_CHARS = 1000;

    public const DEFAULT_CHARS = 250;

    public const MIN_CAPACITY = 2;

    public const MAX_CAPACITY = 8;

    /**
     * Presence-channel authorisation: a member payload, or false.
     *
     * @return array{id:int, name:string}|false
     */
    public function channelAuth(User $user, string $key): array|false
    {
        $race = $this->resolve($key);

        if (! $race || ! $race->participants()->where('user_id', $user->id)->exists()) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * Resolve a channel key ("ABC123" for private, "q<id>" for quick-match) to a race.
     */
    public function resolve(string $key): ?Race
    {
        if (Str::startsWith($key, 'q') && ctype_digit(substr($key, 1))) {
            return Race::find((int) substr($key, 1));
        }

        return Race::where('code', $key)->first();
    }

    /**
     * Drop the user into the oldest open public race, or open a new one.
     */
    public function quickMatch(User $user): Race
    {
        return DB::transaction(function () use ($user): Race {
            $race = Race::where('visibility', 'public')
                ->where('status', 'lobby')
                ->where('created_at', '>=', now()->subSeconds(90))
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get()
                ->first(fn (Race $r): bool => $r->participants()->count() < $r->seatLimit());

            if (! $race) {
                $race = $this->createRace($user, 'public');
            }

            $this->join($race, $user);

            $race->refresh()->loadCount('participants');

            if ($race->status === 'lobby' && $race->participants_count >= Race::MIN_TO_START) {
                $this->beginCountdown($race);
            }

            return $race;
        });
    }

    /**
     * Open a private room the host controls via a share code.
     */
    public function createPrivate(User $user): Race
    {
        return DB::transaction(function () use ($user): Race {
            $race = $this->createRace($user, 'private');
            $this->join($race, $user);

            return $race;
        });
    }

    public function join(Race $race, User $user): RaceParticipant
    {
        $existing = $race->participants()->where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        if (! $race->isJoinable()) {
            throw ValidationException::withMessages(['race' => 'This race can no longer be joined.']);
        }

        $participant = $race->participants()->create([
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);

        broadcast(new RaceLobbyUpdated($race->fresh()));

        return $participant;
    }

    public function leave(Race $race, User $user): void
    {
        if (! in_array($race->status, ['lobby', 'countdown'], true)) {
            return;
        }

        $race->participants()->where('user_id', $user->id)->delete();

        if ($race->participants()->count() === 0) {
            $race->update(['status' => 'abandoned']);

            return;
        }

        broadcast(new RaceLobbyUpdated($race->fresh()));
    }

    public function beginCountdown(Race $race): void
    {
        if ($race->status !== 'lobby') {
            return;
        }

        $race->update([
            'status' => 'countdown',
            'starts_at' => now()->addSeconds(Race::COUNTDOWN_SECONDS),
        ]);

        broadcast(new RaceStarting($race->fresh()));

        StartRaceJob::dispatch($race->id)->delay($race->starts_at);
    }

    public function beginRacing(Race $race): void
    {
        if ($race->status !== 'countdown') {
            return;
        }

        if ($race->participants()->count() < Race::MIN_TO_START && $race->visibility === 'public') {
            $race->update(['status' => 'abandoned']);

            return;
        }

        $race->update(['status' => 'racing']);
        broadcast(new RaceStarted($race->fresh()));
    }

    public function finishParticipant(Race $race, User $user, int $chars, int $correctChars): RaceParticipant
    {
        $participant = $race->participants()->where('user_id', $user->id)->firstOrFail();

        if ($participant->finished_at) {
            return $participant;
        }

        if (! $race->starts_at || now()->lt($race->starts_at)) {
            throw ValidationException::withMessages(['race' => 'This race has not started.']);
        }

        $elapsedMs = max(1000, $race->starts_at->diffInMilliseconds(now()));
        $wpm = (int) min(self::MAX_PLAUSIBLE_WPM, round(($chars / 5) / ($elapsedMs / 60000)));
        $accuracy = $chars > 0 ? round(min(100, $correctChars / $chars * 100), 2) : 0;

        $position = $race->participants()->whereNotNull('finished_at')->count() + 1;

        $test = Test::create([
            'user_id' => $user->id,
            'race_id' => $race->id,
            'quran_text_id' => $race->quran_text_id,
            'mode' => 'quote',
            'duration' => (int) ceil($elapsedMs / 1000),
            'wpm' => $wpm,
            'raw_wpm' => $wpm,
            'accuracy' => $accuracy,
            'char_count' => $chars,
            'correct_chars' => $correctChars,
            'incorrect_chars' => max(0, $chars - $correctChars),
            'start_ayah' => $race->start_ayah,
            'end_ayah' => $race->end_ayah,
            'total_errors' => max(0, $chars - $correctChars),
            'tashkeel' => (bool) $race->tashkeel,
        ]);

        $participant->update([
            'finished_at' => now(),
            'wpm' => $wpm,
            'accuracy' => $accuracy,
            'chars' => $chars,
            'position' => $position,
            'test_id' => $test->id,
        ]);

        broadcast(new RaceParticipantFinished($race->fresh(), $participant->fresh()));

        if ($race->participants()->whereNull('finished_at')->doesntExist()) {
            $this->finish($race);
        }

        return $participant->fresh();
    }

    /**
     * Relay a live progress tick to the room (reliable fallback for client whispers).
     */
    public function relayProgress(Race $race, User $user, float $pct, int $wpm): void
    {
        if ($race->status !== 'racing') {
            return;
        }

        if (! $race->participants()->where('user_id', $user->id)->exists()) {
            return;
        }

        broadcast(new RaceProgress(
            $race->channelKey(),
            $user->id,
            max(0, min(1, $pct)),
            max(0, min(self::MAX_PLAUSIBLE_WPM, $wpm)),
        ));
    }

    public function finish(Race $race): void
    {
        if ($race->status === 'finished') {
            return;
        }

        $race->update(['status' => 'finished', 'finished_at' => now()]);
        broadcast(new RaceFinished($race->fresh()));
    }

    /**
     * Close out races that stalled. Returns the number touched.
     */
    public function reapStale(): int
    {
        $touched = 0;

        Race::where('status', 'countdown')
            ->where('starts_at', '<', now()->subMinutes(3))
            ->each(function (Race $race) use (&$touched): void {
                $race->update(['status' => 'abandoned']);
                $touched++;
            });

        Race::where('status', 'racing')
            ->where('starts_at', '<', now()->subMinutes(10))
            ->each(function (Race $race) use (&$touched): void {
                $this->finish($race);
                $touched++;
            });

        Race::whereIn('status', ['lobby'])
            ->where('created_at', '<', now()->subMinutes(10))
            ->each(function (Race $race) use (&$touched): void {
                $race->update(['status' => 'abandoned']);
                $touched++;
            });

        return $touched;
    }

    /**
     * Update a private room's settings while it is still in the lobby.
     *
     * @param  array<string, mixed>  $params
     */
    public function configure(Race $race, array $params): void
    {
        if ($race->status !== 'lobby' || $race->visibility !== 'private') {
            return;
        }

        $surah = isset($params['scope_surah']) && $params['scope_surah'] !== null
            ? (int) $params['scope_surah']
            : null;

        if ($surah !== null && ! QuranText::where('surah_number', $surah)->exists()) {
            $surah = null;
        }

        $race->update([
            'char_target' => max(self::MIN_CHARS, min(self::MAX_CHARS, (int) ($params['char_target'] ?? self::DEFAULT_CHARS))),
            'tashkeel' => (bool) ($params['tashkeel'] ?? false),
            'capacity' => max(self::MIN_CAPACITY, min(self::MAX_CAPACITY, (int) ($params['capacity'] ?? Race::CAPACITY))),
            'scope_surah' => $surah,
        ]);

        broadcast(new RaceLobbyUpdated($race->fresh()));
    }

    /**
     * Apply the host's settings, build the passage, and kick off the countdown.
     *
     * @param  array<string, mixed>  $params
     */
    public function startPrivate(Race $race, array $params): void
    {
        $this->configure($race, $params);
        $race->refresh();

        [$surah, $start, $end, $quranTextId, $text] = $this->buildPassage(
            (int) ($race->char_target ?: self::DEFAULT_CHARS),
            (bool) $race->tashkeel,
            $race->scope_surah,
        );

        $race->update([
            'quran_text_id' => $quranTextId,
            'surah_number' => $surah,
            'start_ayah' => $start,
            'end_ayah' => $end,
            'text' => $text,
        ]);

        $this->beginCountdown($race->fresh());
    }

    private function createRace(User $user, string $visibility): Race
    {
        if ($visibility === 'private') {
            return Race::create([
                'code' => $this->uniqueCode(),
                'visibility' => 'private',
                'status' => 'lobby',
                'host_user_id' => $user->id,
                'char_target' => self::DEFAULT_CHARS,
                'tashkeel' => false,
                'capacity' => Race::CAPACITY,
            ]);
        }

        [$surah, $start, $end, $quranTextId, $text] = $this->buildPassage(self::DEFAULT_CHARS, false, null);

        return Race::create([
            'code' => null,
            'visibility' => 'public',
            'status' => 'lobby',
            'host_user_id' => $user->id,
            'quran_text_id' => $quranTextId,
            'surah_number' => $surah,
            'start_ayah' => $start,
            'end_ayah' => $end,
            'text' => $text,
            'char_target' => mb_strlen($text),
            'tashkeel' => false,
            'capacity' => Race::CAPACITY,
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Race::where('code', $code)->exists());

        return $code;
    }

    /**
     * Pick a random run of consecutive ayahs long enough to reach the character
     * target, staying within one surah (and within `scopeSurah` when given).
     *
     * @return array{0:int,1:int,2:int,3:int,4:string} [surah, start, end, quranTextId, text]
     */
    private function buildPassage(int $charTarget, bool $tashkeel, ?int $scopeSurah): array
    {
        $column = $tashkeel ? 'surah_arabic_ponctuation' : 'text_arabic_simple';
        $charTarget = max(self::MIN_CHARS, min(self::MAX_CHARS, $charTarget));

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $surahNumber = $scopeSurah
                ?? (int) QuranText::query()->inRandomOrder()->value('surah_number');

            $surahAyahs = QuranText::where('surah_number', $surahNumber)
                ->orderBy('ayah_number')
                ->get(['id', 'ayah_number', 'text_arabic_simple', 'surah_arabic_ponctuation']);

            if ($surahAyahs->isEmpty()) {
                continue;
            }

            $startIdx = random_int(0, max(0, $surahAyahs->count() - 1));
            $picked = collect();
            $length = 0;

            for ($i = $startIdx; $i < $surahAyahs->count() && $i < $startIdx + 15; $i++) {
                $picked->push($surahAyahs[$i]);
                $length += mb_strlen(trim((string) ($surahAyahs[$i]->{$column} ?: $surahAyahs[$i]->text_arabic_simple)));
                if ($length >= $charTarget) {
                    break;
                }
            }

            // Not enough room after the random start? Slide the window back.
            if ($length < $charTarget && $startIdx > 0) {
                $picked = collect();
                $length = 0;
                for ($i = $surahAyahs->count() - 1; $i >= 0; $i--) {
                    $picked->prepend($surahAyahs[$i]);
                    $length += mb_strlen(trim((string) ($surahAyahs[$i]->{$column} ?: $surahAyahs[$i]->text_arabic_simple)));
                    if ($length >= $charTarget) {
                        break;
                    }
                }
            }

            if ($picked->isNotEmpty()) {
                $text = $this->assembleText($picked, $column);

                return [
                    $surahNumber,
                    (int) $picked->first()->ayah_number,
                    (int) $picked->last()->ayah_number,
                    (int) $picked->first()->id,
                    $text,
                ];
            }
        }

        // Fallback: Al-Fatiha, whole surah.
        $ayahs = QuranText::where('surah_number', 1)->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'text_arabic_simple', 'surah_arabic_ponctuation']);

        return [1, 1, (int) $ayahs->last()->ayah_number, (int) $ayahs->first()->id, $this->assembleText($ayahs, $column)];
    }

    /**
     * @param  Collection<int, QuranText>  $ayahs
     */
    private function assembleText(Collection $ayahs, string $column = 'text_arabic_simple'): string
    {
        return trim($ayahs->map(
            fn (QuranText $a): string => trim((string) ($a->{$column} ?: $a->text_arabic_simple)).' ۝'.$this->arabicDigits((int) $a->ayah_number).' '
        )->implode(''));
    }

    private function arabicDigits(int $number): string
    {
        return strtr((string) $number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }
}
