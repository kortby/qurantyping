<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\HifzService;
use App\Services\StreakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Columns the users list may be sorted by.
     *
     * @var list<string>
     */
    protected array $sortable = [
        'name', 'email', 'tests_count', 'email_verified_at', 'created_at',
        'current_streak', 'hifz_ayahs', 'certificates_count',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sort = in_array($request->query('sort'), $this->sortable, true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $today = Carbon::now(config('app.timezone'))->toDateString();

        return Inertia::render('Admin/Users/Index', [
            'filters' => ['search' => $search ?: null, 'sort' => $sort, 'direction' => $direction],
            'users' => fn () => User::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->withCount([
                    'tests',
                    'hifzProgress as hifz_ayahs',
                    'hifzProgress as hifz_due' => fn ($q) => $q->whereDate('due_on', '<=', $today),
                    'certificates as certificates_count',
                ])
                ->orderBy($sort, $direction)
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'tests_count' => $user->tests_count,
                    'current_streak' => (int) $user->current_streak,
                    'last_practiced_on' => $user->last_practiced_on?->toDateString(),
                    'hifz_ayahs' => (int) $user->hifz_ayahs,
                    'hifz_due' => (int) $user->hifz_due,
                    'certificates_count' => (int) $user->certificates_count,
                    'created_at' => $user->created_at,
                    'is_super_admin' => $user->isSuperAdmin(),
                ]),
        ]);
    }

    public function show(
        Request $request,
        User $user,
        StreakService $streaks,
        HifzService $hifz,
        CertificateService $certificates,
    ): Response {
        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'profile_photo_url' => $user->profile_photo_url,
                'oauth_provider' => $user->oauth_provider,
                'is_super_admin' => $user->isSuperAdmin(),
                'is_self' => $user->is($request->user()),
                'last_practiced_on' => $user->last_practiced_on?->toDateString(),
            ],
            'stats' => fn (): array => [
                'tests_count' => (int) $user->tests()->count(),
                'best_wpm' => (int) $user->tests()->max('wpm'),
                'avg_wpm' => (int) round((float) $user->tests()->avg('wpm')),
            ],
            'progress' => fn (): array => [
                'streak' => $streaks->forInertia($user),
                'hifz' => $hifz->stats($user) + [
                    'daily_new' => (int) $user->hifz_daily_new,
                    'auto_advance' => (bool) $user->auto_advance,
                ],
                'certificates' => $certificates->forUser($user)->map(fn ($c): array => [
                    'surah_number' => $c->surah_number,
                    'surah_name_english' => $c->surah_name_english,
                    'surah_name_arabic' => $c->surah_name_arabic,
                    'ayah_count' => $c->ayah_count,
                    'accuracy' => (float) $c->accuracy,
                    'issued_at' => $c->issued_at->toDateString(),
                ]),
            ],
            'badges' => fn () => $user->badges()->get(['badges.id', 'name', 'icon']),
            'recentTests' => fn () => $user->tests()
                ->latest()
                ->take(10)
                ->get(['id', 'wpm', 'accuracy', 'char_count', 'total_errors', 'created_at']),
            'sessions' => fn (): array => $this->sessionsFor($request, $user),
            'tokens' => fn () => $user->tokens()->latest()->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->safe()->only(['name', 'email']));

        if ($request->boolean('mark_verified') && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return back()->with('message', 'User updated.');
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('message', 'User email is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('message', 'Verification email sent.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()) || $user->isSuperAdmin(), 403);

        $user->delete();

        return redirect()->route('admin.users.index')->with('message', 'User deleted.');
    }

    public function revokeSessions(User $user): RedirectResponse
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('message', 'Browser sessions revoked.');
    }

    public function destroyToken(User $user, string $token): RedirectResponse
    {
        $user->tokens()->whereKey($token)->delete();

        return back()->with('message', 'API token revoked.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sessionsFor(Request $request, User $user): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session): array => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => Str::limit((string) $session->user_agent, 180),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'is_current_device' => $session->id === $request->session()->getId(),
            ])
            ->all();
    }
}
