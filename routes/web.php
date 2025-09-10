<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\WaitlistController;

Route::get('/', function () {
    return Inertia::render('Landing');
})->name('landing');

Route::get('/home', function () {
    return redirect('/');
})->name('home');

Route::post('/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
