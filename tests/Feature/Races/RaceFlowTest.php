<?php

use App\Events\Race\RaceFinished;
use App\Events\Race\RaceParticipantFinished;
use App\Events\Race\RaceProgress;
use App\Events\Race\RaceStarting;
use App\Jobs\StartRaceJob;
use App\Models\QuranText;
use App\Models\Race;
use App\Models\Test;
use App\Models\User;
use App\Services\RaceService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

function seedRaceAyahs(): void
{
    $rows = [];
    for ($a = 1; $a <= 6; $a++) {
        $rows[] = [
            'surah_number' => 113, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 604,
            'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
            'surah_name_arabic' => 'الفلق', 'surah_name_english' => 'Al-Falaq', 'surah_name_translation' => 'Al-Falaq',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);
}

beforeEach(function () {
    seedRaceAyahs();
    Queue::fake();
});

it('drops a second quick-match player into the same race and starts the countdown', function () {
    Event::fake([RaceStarting::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();

    $raceA = app(RaceService::class)->quickMatch($a);
    expect($raceA->status)->toBe('lobby');

    $raceB = app(RaceService::class)->quickMatch($b);

    expect($raceB->id)->toBe($raceA->id)
        ->and($raceB->fresh()->status)->toBe('countdown')
        ->and($raceB->fresh()->starts_at)->not->toBeNull();

    Event::assertDispatched(RaceStarting::class);
    Queue::assertPushed(StartRaceJob::class);
});

it('rejects joining a full race and quick-match opens a new one', function () {
    $service = app(RaceService::class);
    $race = Race::factory()->create(['status' => 'lobby', 'visibility' => 'public']);

    foreach (range(1, Race::CAPACITY) as $ignored) {
        $service->join($race->fresh(), User::factory()->create());
    }

    expect($race->fresh()->participants()->count())->toBe(Race::CAPACITY);

    expect(fn () => $service->join($race->fresh(), User::factory()->create()))
        ->toThrow(ValidationException::class);

    $fresh = $service->quickMatch(User::factory()->create());
    expect($fresh->id)->not->toBe($race->id);
});

it('creates a private room the host controls', function () {
    $host = User::factory()->create();

    $race = app(RaceService::class)->createPrivate($host);

    expect($race->code)->toHaveLength(6)
        ->and($race->visibility)->toBe('private')
        ->and($race->participants()->where('user_id', $host->id)->exists())->toBeTrue();

    // A non-host cannot start it.
    $intruder = User::factory()->create();
    actingAs($intruder)->post("/races/{$race->code}/start")->assertForbidden();

    actingAs($host)->post("/races/{$race->code}/start")->assertRedirect();
    expect($race->fresh()->status)->toBe('countdown');
});

it('records a finish, writes a Test, and orders positions by finish order', function () {
    Event::fake([RaceParticipantFinished::class, RaceFinished::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $service = app(RaceService::class);

    $race = $service->quickMatch($a);
    $service->quickMatch($b);
    $race->refresh()->update(['status' => 'racing', 'starts_at' => now()->subSeconds(20)]);

    $pa = $service->finishParticipant($race->fresh(), $a, 200, 190);
    $pb = $service->finishParticipant($race->fresh(), $b, 150, 150);

    expect($pa->position)->toBe(1)
        ->and($pb->position)->toBe(2)
        ->and($pa->wpm)->toBeLessThanOrEqual(RaceService::MAX_PLAUSIBLE_WPM)
        ->and(Test::where('race_id', $race->id)->count())->toBe(2)
        ->and(Test::where('race_id', $race->id)->where('user_id', $a->id)->value('accuracy'))->toEqual('95.00');

    expect($race->fresh()->status)->toBe('finished');
    Event::assertDispatched(RaceFinished::class);
});

it('applies the host settings when starting a private room', function () {
    $host = User::factory()->create();
    $race = app(RaceService::class)->createPrivate($host);

    expect($race->text)->toBeNull()
        ->and($race->quran_text_id)->toBeNull();

    actingAs($host)->post("/races/{$race->code}/start", [
        'char_target' => 150,
        'capacity' => 8,
        'tashkeel' => true,
        'scope_surah' => 113,
    ])->assertRedirect();

    $race->refresh();

    expect($race->status)->toBe('countdown')
        ->and($race->char_target)->toBe(150)
        ->and($race->capacity)->toBe(8)
        ->and($race->tashkeel)->toBeTrue()
        ->and($race->surah_number)->toBe(113)
        ->and($race->text)->not->toBeNull()
        ->and($race->quran_text_id)->not->toBeNull();
});

it('rejects an out-of-range char target', function () {
    $host = User::factory()->create();
    $race = app(RaceService::class)->createPrivate($host);

    actingAs($host)->post("/races/{$race->code}/start", ['char_target' => 20])
        ->assertSessionHasErrors('char_target');

    expect($race->fresh()->status)->toBe('lobby');
});

it('relays a progress tick to the room while racing', function () {
    Event::fake([RaceProgress::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $service = app(RaceService::class);

    $race = $service->quickMatch($a);
    $service->quickMatch($b);
    $race->refresh()->update(['status' => 'racing', 'starts_at' => now()->subSeconds(5)]);

    actingAs($a)->post("/races/{$race->channelKey()}/progress", ['pct' => 0.4, 'wpm' => 55])
        ->assertOk();

    Event::assertDispatched(RaceProgress::class, fn ($e) => $e->userId === $a->id && $e->pct === 0.4);

    // A non-racing race relays nothing.
    Event::fake([RaceProgress::class]);
    $race->update(['status' => 'lobby']);
    actingAs($a)->post("/races/{$race->channelKey()}/progress", ['pct' => 0.9, 'wpm' => 80])->assertOk();
    Event::assertNotDispatched(RaceProgress::class);
});

it('rejects a finish posted before the race starts', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $service = app(RaceService::class);

    $race = $service->quickMatch($a);
    $service->quickMatch($b);
    // still in countdown, starts_at in the future

    expect(fn () => $service->finishParticipant($race->fresh(), $a, 100, 100))
        ->toThrow(ValidationException::class);
});

it('caps an implausible WPM', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $service = app(RaceService::class);

    $race = $service->quickMatch($a);
    $service->quickMatch($b);
    $race->refresh()->update(['status' => 'racing', 'starts_at' => now()->subSeconds(1)]);

    $p = $service->finishParticipant($race->fresh(), $a, 5000, 5000);

    expect($p->wpm)->toBe(RaceService::MAX_PLAUSIBLE_WPM);
});

it('reaps a countdown race left short of players', function () {
    $lonely = Race::factory()->create([
        'status' => 'countdown',
        'starts_at' => now()->subMinutes(5),
    ]);
    Race::factory()->create(['status' => 'lobby', 'created_at' => now()->subMinutes(20)]);

    $touched = app(RaceService::class)->reapStale();

    expect($touched)->toBeGreaterThanOrEqual(1)
        ->and($lonely->fresh()->status)->toBe('abandoned');
});

it('requires auth for the races area', function () {
    $this->get('/races')->assertRedirect('/login');
    $this->post('/races/quick')->assertRedirect('/login');
});

it('authorises the presence channel only for participants', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $race = app(RaceService::class)->createPrivate($member);
    $service = app(RaceService::class);

    expect($service->channelAuth($member, $race->code))->toMatchArray(['id' => $member->id])
        ->and($service->channelAuth($outsider, $race->code))->toBeFalse()
        ->and($service->channelAuth($member, 'NOPE99'))->toBeFalse();
});
