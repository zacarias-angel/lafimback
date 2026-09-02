<?php

use App\Http\Controllers\AdminResourceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClubAdminController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('matches/{scope}', [PublicController::class, 'matches'])->whereIn('scope', ['upcoming', 'today', 'completed']);
Route::get('standings/{tournament}/{category}', [PublicController::class, 'standings']);
Route::get('standings/{category}', [PublicController::class, 'currentStandings']);
Route::get('clubs', [PublicController::class, 'clubs']);
Route::get('clubs/{club:slug}', [PublicController::class, 'club']);
Route::get('players', [PublicController::class, 'players']);
Route::get('news', [PublicController::class, 'news']);
Route::get('news/{news:slug}', [PublicController::class, 'newsShow']);
// Spanish compatibility routes used by the independently deployed public frontend.
Route::get('partidos/{scope}', [PublicController::class, 'spanishMatches'])->whereIn('scope', ['proximos', 'hoy', 'jugados']);
Route::get('clubes', [PublicController::class, 'spanishClubs']);
Route::get('clubes/{club}', [PublicController::class, 'spanishClub']);
Route::get('jugadores', [PublicController::class, 'spanishPlayers']);
Route::get('noticias', [PublicController::class, 'spanishNews']);
Route::get('noticias/{news:slug}', [PublicController::class, 'spanishNewsShow']);
Route::get('posiciones/{category}', [PublicController::class, 'spanishStandings']);

Route::middleware('api.token')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('matches/{match}/results/submissions', [ResultController::class, 'submit'])->middleware('role:SUPER_ADMIN,CLUB_ADMIN');
    Route::post('matches/{match}/results/confirm', [ResultController::class, 'confirm'])->middleware('role:SUPER_ADMIN,CLUB_ADMIN');
    Route::post('matches/{match}/results/validate', [ResultController::class, 'validateResult'])->middleware('role:SUPER_ADMIN');
    Route::prefix('club-admin')->middleware('role:CLUB_ADMIN')->group(function () {
        Route::get('dashboard', [ClubAdminController::class, 'dashboard']);
        Route::patch('clubs/{club}', [ClubAdminController::class, 'updateClub'])->middleware('club.access:club');
        Route::post('clubs/{club}/players', [ClubAdminController::class, 'storePlayer'])->middleware('club.access:club');
    });
    Route::prefix('admin')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('roles', [AdminUserController::class, 'roles']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::get('users/{id}', [AdminUserController::class, 'show']);
        Route::match(['put', 'patch'], 'users/{id}', [AdminUserController::class, 'update']);
        Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
        Route::get('{resource}', [AdminResourceController::class, 'index']);
        Route::post('{resource}', [AdminResourceController::class, 'store']);
        Route::get('{resource}/{id}', [AdminResourceController::class, 'show']);
        Route::match(['put', 'patch'], '{resource}/{id}', [AdminResourceController::class, 'update']);
        Route::delete('{resource}/{id}', [AdminResourceController::class, 'destroy']);
    });
});
