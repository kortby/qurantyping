<?php

namespace App\Providers;

use App\Models\Friendship;
use App\Models\Test;
use App\Models\User;
use App\Observers\TestObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewAdmin', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('viewPulse', fn (User $user): bool => $user->isSuperAdmin());

        Test::observe(TestObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            // Don't count impersonation as the user signing in themselves.
            if (! request()->routeIs('admin.users.impersonate', 'impersonate.leave')) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }

            // Redeem a friend invite the guest followed before signing up.
            if ($token = request()->session()?->pull('pending_invite')) {
                $owner = User::where('invite_token', $token)->first();

                if ($owner && $owner->id !== $event->user->id) {
                    Friendship::request($event->user, $owner->id);
                }
            }
        });
    }
}
