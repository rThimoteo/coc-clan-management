<?php

use App\Http\Controllers\Admin\ClanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarController;
use App\Models\War;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'activeWar' => War::query()
            ->active()
            ->latest('end_time')
            ->first(),
    ]);
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::post('/members/sync', [MemberController::class, 'sync'])
        ->middleware('can:sync-clan-data')
        ->name('members.sync');
    Route::get('/wars', [WarController::class, 'index'])->name('wars.index');
    Route::post('/wars/sync', [WarController::class, 'sync'])
        ->middleware('can:sync-clan-data')
        ->name('wars.sync');
    Route::get('/wars/{war}', [WarController::class, 'show'])->name('wars.show');

    Route::middleware('can:manage-clan')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/clan', [ClanController::class, 'edit'])->name('clan.edit');
        Route::patch('/clan', [ClanController::class, 'update'])->name('clan.update');
    });

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/access-code', [UserController::class, 'regenerate'])->name('users.access-code.regenerate');
        Route::put('/users/{user}/members', [UserController::class, 'updateMembers'])->name('users.members.update');
    });
});

require __DIR__.'/auth.php';
