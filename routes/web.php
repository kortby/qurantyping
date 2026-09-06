<?php

use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ContestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrillController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HifzController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TestPageController;
use App\Http\Controllers\UserSettingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', TestPageController::class);
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/contest', [ContestController::class, 'index'])->name('contest.index');
Route::get('/privacy-policy', function () {
    $lang = App::getLocale();
    $file = resource_path("markdown/policy.{$lang}.md");

    if (! file_exists($file)) {
        $file = resource_path('markdown/policy.md');
    }

    return Inertia::render('PrivacyPolicy', [
        'policy' => Str::markdown(file_get_contents($file)),
    ]);
})->name('privacy.policy');
Route::get('/terms-of-service', function () {
    $lang = App::getLocale();
    $file = resource_path("markdown/terms.{$lang}.md");

    if (! file_exists($file)) {
        $file = resource_path('markdown/terms.md');
    }

    return Inertia::render('TermsOfService', [
        'terms' => Str::markdown(file_get_contents($file)),
    ]);
})->name('terms.show');
Route::get('/work-in-progress', function () {
    return Inertia::render('WorkInProgress');
})->name('wip');

Route::get('/data-deletion', function () {
    return Inertia::render('DataDeletion', [
        'content' => Str::markdown(file_get_contents(resource_path('markdown/data_deletion.md'))),
    ]);
})->name('data.deletion');

Route::get('/debug-lang', function () {
    return response()->json([
        'cookie_lang' => request()->cookie('lang'),
        'all_cookies' => request()->cookies->all(),
    ]);
});

Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// Social Authentication
Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('social.callback');

// This is the default route, you can leave it or remove it.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Typing Test Routes
Route::get('api/test/new', [TestController::class, 'getNewTest']);

Route::get('api/surahs', [TestController::class, 'getSurahs']);
Route::get('api/quran/scopes', [TestController::class, 'getScopes']);
Route::get('api/test/text', [TestController::class, 'getTextForTest']);
Route::post('/test/complete', [TestController::class, 'store']);

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::post('/user/settings/error-sound', [UserSettingController::class, 'updateErrorSound'])->name('user.settings.error-sound');
    Route::post('/user/settings/daily-goal', [UserSettingController::class, 'updateDailyGoal'])->name('user.settings.daily-goal');
    Route::post('/user/settings/auto-advance', [UserSettingController::class, 'updateAutoAdvance'])->name('user.settings.auto-advance');
    Route::post('/user/settings/hifz-daily-new', [UserSettingController::class, 'updateHifzDailyNew'])->name('user.settings.hifz-daily-new');
    Route::post('/user/settings/reciter', [UserSettingController::class, 'updateReciter'])->name('user.settings.reciter');

    Route::get('/hifz', [HifzController::class, 'index'])->name('hifz.index');
    Route::get('/hifz/session', [HifzController::class, 'session'])->name('hifz.session');
    Route::post('/hifz/grade', [HifzController::class, 'grade'])->name('hifz.grade');

    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');

    Route::get('/drills', DrillController::class)->name('drills.index');
    Route::get('/test/drill', [TestController::class, 'drillText'])->name('test.drill');
});

// Admin users management (super admins only).
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'can:viewAdmin',
])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/resend-verification', [AdminUserController::class, 'resendVerification'])->name('users.resend-verification');
    Route::delete('users/{user}/sessions', [AdminUserController::class, 'revokeSessions'])->name('users.sessions.revoke');
    Route::delete('users/{user}/tokens/{token}', [AdminUserController::class, 'destroyToken'])->name('users.tokens.destroy');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

    Route::get('feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
    Route::patch('feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');
    Route::delete('feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');
});

// Reachable while impersonating (the active user is not an admin at that point).
Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])
    ->middleware(['auth:sanctum', config('jetstream.auth_session')])
    ->name('impersonate.leave');
