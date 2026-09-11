<?php

namespace App\Providers;

use App\Models\ClassGroup;
use App\Models\Friendship;
use App\Models\Test;
use App\Models\User;
use App\Observers\TestObserver;
use App\Services\ClassService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        View::composer('app', function ($view): void {
            $view->with('structuredDataJson', $this->structuredData((array) View::shared('faq', [])));
        });

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

            // Redeem a class join code the guest followed before signing up.
            if ($code = request()->session()?->pull('pending_class_code')) {
                $class = ClassGroup::where('code', $code)->first();

                if ($class) {
                    app(ClassService::class)->join($class, $event->user);
                }
            }
        });
    }

    /**
     * Build the site-wide JSON-LD graph, optionally with a FAQ block.
     *
     * @param  list<array{q: string, a: string}>  $faq
     */
    private function structuredData(array $faq): string
    {
        $site = rtrim((string) config('app.url'), '/');

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $site.'/#organization',
                'name' => 'QuranTyping',
                'url' => $site,
                'logo' => $site.'/favicon.svg',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $site.'/#website',
                'url' => $site,
                'name' => 'QuranTyping',
                'publisher' => ['@id' => $site.'/#organization'],
                'inLanguage' => ['en', 'fr', 'ar'],
            ],
            [
                '@type' => 'WebApplication',
                '@id' => $site.'/#webapp',
                'name' => 'QuranTyping',
                'url' => $site,
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem' => 'Any (web browser)',
                'browserRequirements' => 'Requires JavaScript',
                'description' => "Free web app to practise typing the Qur'an in Arabic with real-time accuracy feedback, full tashkil, an on-screen Arabic keyboard, and a spaced-repetition Hifz mode.",
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'isAccessibleForFree' => true,
                'publisher' => ['@id' => $site.'/#organization'],
            ],
        ];

        if ($faq !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ], $faq),
            ];
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG,
        );
    }
}
