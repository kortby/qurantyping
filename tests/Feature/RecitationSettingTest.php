<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('saves a valid reciter preference', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/user/settings/reciter', ['reciter' => 'husary'])->assertRedirect();

    expect($user->fresh()->reciter)->toBe('husary');
});

it('rejects an unknown reciter', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/user/settings/reciter', ['reciter' => 'not_a_reciter'])
        ->assertSessionHasErrors('reciter');

    expect($user->fresh()->reciter)->toBeNull();
});

it('requires authentication', function () {
    $this->post('/user/settings/reciter', ['reciter' => 'husary'])->assertRedirect('/login');
});

it('shares the reciter preference and catalogue with the frontend', function () {
    $user = User::factory()->create(['reciter' => 'husary']);

    actingAs($user)->get('/')->assertInertia(fn ($page) => $page
        ->where('auth.user.reciter', 'husary')
        ->has('reciters.husary')
        ->has('reciters.alafasy')
    );
});

it('falls back to the default reciter when the user has not chosen one', function () {
    $user = User::factory()->create(['reciter' => null]);

    actingAs($user)->get('/')->assertInertia(fn ($page) => $page
        ->where('auth.user.reciter', config('reciters.default'))
    );
});
