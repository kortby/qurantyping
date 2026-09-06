<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Feedback;
use App\Models\RaceParticipant;
use App\Models\User;
use App\Models\UserLetterStat;
use App\Services\CertificateService;
use App\Services\HifzService;
use App\Services\QuranMapService;
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
        'name', 'email', 'tests_count', 'created_at', 'last_login_at',
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
                    'last_login_at' => $user->last_login_at,
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
        QuranMapService $map,
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
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'account' => [
                'two_factor' => ! is_null($user->two_factor_confirmed_at),
                'reciter' => $user->reciter ?? config('reciters.default'),
                'error_sound' => (bool) $user->error_sound,
                'auto_advance' => (bool) $user->auto_advance,
                'daily_goal_chars' => (int) $user->daily_goal_chars,
                'hifz_daily_new' => (int) $user->hifz_daily_new,
                'streak_grace_used_on' => $user->streak_grace_used_on?->toDateString(),
            ],
            'stats' => fn (): array => $this->statsFor($user),
            'quran' => fn (): array => $map->overview($user)['totals'],
            'races' => fn (): array => $this->racesFor($user),
            'weakLetters' => fn () => UserLetterStat::query()
                ->where('user_id', $user->id)
                ->where('attempts', '>=', 5)
                ->get()
                ->map(fn (UserLetterStat $s): array => [
                    'character' => $s->character,
                    'attempts' => (int) $s->attempts,
                    'misses' => (int) $s->misses,
                    'accuracy' => round(100 * (1 - $s->misses / max(1, $s->attempts)), 1),
                ])
                ->sortBy('accuracy')
                ->take(8)
                ->values(),
            'activity' => fn () => DB::table('daily_activity')
                ->where('user_id', $user->id)
                ->orderByDesc('date')
                ->limit(30)
                ->get(['date', 'tests_count', 'chars', 'seconds'])
                ->reverse()
                ->map(fn ($r): array => [
                    'date' => (string) $r->date,
                    'tests' => (int) $r->tests_count,
                    'chars' => (int) $r->chars,
                    'minutes' => (int) round($r->seconds / 60),
                ])
                ->values(),
            'feedback' => fn () => Feedback::query()
                ->where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Feedback $f): array => [
                    'id' => $f->id,
                    'type' => $f->type,
                    'excerpt' => Str::limit((string) $f->message, 120),
                    'handled' => ! is_null($f->handled_at),
                    'created_at' => $f->created_at->toIso8601String(),
                ]),
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
            'badges' => fn () => $user->badges()
                ->get(['badges.id', 'name', 'icon', 'slug'])
                ->map(fn ($b): array => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'icon' => $b->icon,
                    'slug' => $b->slug,
                    'awarded_at' => $b->pivot->awarded_at,
                ]),
            'badgeTotal' => count(config('badges.list')),
            'recentTests' => fn () => $user->tests()
                ->with('quranText:id,surah_number')
                ->latest()
                ->take(15)
                ->get(['id', 'quran_text_id', 'wpm', 'accuracy', 'char_count', 'total_errors', 'mode', 'hifz_level', 'tashkeel', 'race_id', 'start_ayah', 'end_ayah', 'created_at'])
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'wpm' => (int) $t->wpm,
                    'accuracy' => (float) $t->accuracy,
                    'char_count' => (int) $t->char_count,
                    'total_errors' => (int) $t->total_errors,
                    'mode' => $t->hifz_level ? 'hifz' : ($t->race_id ? 'race' : $t->mode),
                    'tashkeel' => (bool) $t->tashkeel,
                    'range' => $t->quranText ? $t->quranText->surah_number.':'.$t->start_ayah.'–'.$t->end_ayah : null,
                    'created_at' => $t->created_at->toIso8601String(),
                ]),
            'sessions' => fn (): array => $this->sessionsFor($request, $user),
            'tokens' => fn () => $user->tokens()->latest()->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function statsFor(User $user): array
    {
        $tests = $user->tests();
        $daily = DB::table('daily_activity')->where('user_id', $user->id);

        return [
            'tests_count' => (int) $tests->clone()->count(),
            'best_wpm' => (int) $tests->clone()->max('wpm'),
            'avg_wpm' => (int) round((float) $tests->clone()->avg('wpm')),
            'avg_accuracy' => round((float) $tests->clone()->avg('accuracy'), 1),
            'total_chars' => (int) $tests->clone()->sum('char_count'),
            'total_errors' => (int) $tests->clone()->sum('total_errors'),
            'tashkeel_tests' => (int) $tests->clone()->where('tashkeel', true)->count(),
            'contest_tests' => (int) $tests->clone()->where('is_contest_entry', true)->count(),
            'first_test_at' => optional($tests->clone()->min('created_at'))
                ? Carbon::parse($tests->clone()->min('created_at'))->toIso8601String()
                : null,
            'active_days' => (int) $daily->clone()->count(),
            'hours_practiced' => round((int) $daily->clone()->sum('seconds') / 3600, 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function racesFor(User $user): array
    {
        $p = RaceParticipant::where('user_id', $user->id);

        return [
            'finished' => (int) $p->clone()->whereNotNull('finished_at')->count(),
            'wins' => (int) $p->clone()->where('position', 1)->count(),
            'podiums' => (int) $p->clone()->whereBetween('position', [1, 3])->count(),
            'best_wpm' => (int) $p->clone()->max('wpm'),
            'last_at' => optional($p->clone()->whereNotNull('finished_at')->max('finished_at'))
                ? Carbon::parse($p->clone()->whereNotNull('finished_at')->max('finished_at'))->toIso8601String()
                : null,
        ];
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
