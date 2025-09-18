<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\WaitlistController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Landing');
})->name('landing');

Route::get('/home', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Landing');
})->name('home');

Route::post('/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/projects', [App\Http\Controllers\ProjectsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.index');

Route::post('/dashboard/projects', [App\Http\Controllers\ProjectsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.store');

Route::get('/dashboard/projects/{project}/tasks', [App\Http\Controllers\TasksController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.index');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
