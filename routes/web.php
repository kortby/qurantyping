<?php

use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\TestPageController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', TestPageController::class);
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/privacy-policy', function () {
    return Inertia::render('PrivacyPolicy'); })->name('privacy.policy');
Route::get('/terms-of-service', function () {
    return Inertia::render('TermsOfService'); })->name('terms.show');
Route::get('/work-in-progress', function () {
    return Inertia::render('WorkInProgress'); })->name('wip');

// Social Authentication
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Auth\SocialiteController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Auth\SocialiteController::class, 'callback'])->name('social.callback');


// This is the default route, you can leave it or remove it.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Typing Test Routes
Route::get('api/test/new', [TestController::class, 'getNewTest']);

Route::get('api/surahs', [TestController::class, 'getSurahs']);
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
});
