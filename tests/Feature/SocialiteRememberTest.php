<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\get;

function fakeSocialUser(string $id, string $email): SocialiteUser
{
    $user = Mockery::mock(SocialiteUser::class);
    $user->shouldReceive('getId')->andReturn($id);
    $user->shouldReceive('getEmail')->andReturn($email);
    $user->shouldReceive('getName')->andReturn('Test User');
    $user->shouldReceive('getNickname')->andReturn(null);

    return $user;
}

function mockSocialiteReturning(SocialiteUser $socialUser): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialUser);
    Socialite::shouldReceive('driver')->andReturn($provider);
}

it('issues a remember cookie when logging in via oauth', function () {
    $existing = User::factory()->create([
        'email' => 'oauth@example.com',
        'oauth_provider' => 'google',
        'oauth_id' => '12345',
    ]);

    mockSocialiteReturning(fakeSocialUser('12345', 'oauth@example.com'));

    $response = get('/auth/google/callback');

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($existing);

    $names = collect($response->headers->getCookies())->map->getName();
    expect($names->contains(fn (string $n): bool => str_starts_with($n, 'remember_web')))->toBeTrue();

    $existing->refresh();
    expect($existing->remember_token)->not->toBeNull();
});
