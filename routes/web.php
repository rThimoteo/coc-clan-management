<?php

use App\Http\Controllers\Admin\ClanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ClanContextController;
use App\Http\Controllers\CwlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::put('/clan-context', [ClanContextController::class, 'update'])
        ->name('clan-context.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::post('/members/sync', [MemberController::class, 'sync'])
        ->middleware('can:sync-clan-data')
        ->name('members.sync');
    Route::get('/members/{membership}', [MemberController::class, 'show'])
        ->name('members.show');
    Route::get('/wars', [WarController::class, 'index'])->name('wars.index');
    Route::post('/wars/sync', [WarController::class, 'sync'])
        ->middleware('can:sync-clan-data')
        ->name('wars.sync');
    Route::get('/wars/{war}', [WarController::class, 'show'])->name('wars.show');
    Route::get('/cwl', [CwlController::class, 'index'])->name('cwl.index');
    Route::post('/cwl/sync', [CwlController::class, 'sync'])
        ->middleware('can:sync-clan-data')
        ->name('cwl.sync');
    Route::get('/cwl/{league}/wars/{war}', [CwlController::class, 'war'])
        ->name('cwl.wars.show');
    Route::get('/cwl/{league}', [CwlController::class, 'show'])->name('cwl.show');

    Route::middleware('can:manage-clan')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/clans', [ClanController::class, 'index'])->name('clans.index');
        Route::post('/clans', [ClanController::class, 'store'])->name('clans.store');
        Route::patch('/clans/{clan}', [ClanController::class, 'refresh'])
            ->name('clans.refresh');
        Route::patch('/clans/{clan}/default', [ClanController::class, 'setDefault'])
            ->name('clans.default');
        Route::delete('/clans/{clan}', [ClanController::class, 'destroy'])
            ->name('clans.destroy');
    });

    Route::middleware('can:view-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->middleware('can:manage-user-roles')
            ->name('users.role.update');
        Route::put('/users/{user}/players', [UserController::class, 'updatePlayers'])
            ->middleware('can:link-user-players')
            ->name('users.players.update');
    });

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/access-code', [UserController::class, 'regenerate'])->name('users.access-code.regenerate');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
