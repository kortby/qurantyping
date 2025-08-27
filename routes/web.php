<?php

use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\TestPageController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', TestPageController::class);


// This is the default route, you can leave it or remove it.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Typing Test Routes
Route::get('api/test/new', [TestController::class, 'getNewTest']);
Route::post('/test/complete', [TestController::class, 'store']);

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

// Route::middleware([
//     'auth:sanctum',
//     config('jetstream.auth_session'),
//     'verified',
// ])->group(function () {
//     Route::get('/dashboard', function () {
//         return Inertia::render('Dashboard');
//     })->name('dashboard');
// });
