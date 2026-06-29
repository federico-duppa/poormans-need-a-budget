<?php

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('budget')
        : redirect()->route('login');
})->name('home');

// --- Autenticación ---------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::view('/login', 'welcome')->name('login');

    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::post('/logout', [GoogleController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// --- App (autenticada + whitelist) ----------------------------------------
Route::middleware(['auth', 'whitelisted'])->group(function () {
    Route::view('/budget', 'app.budget')->name('budget');
    Route::view('/accounts', 'app.accounts')->name('accounts');
    Route::view('/transactions', 'app.transactions')->name('transactions');
    Route::view('/transactions/new', 'app.transaction-new')->name('transactions.new');
    Route::view('/reports', 'app.placeholder')->name('reports');
});
