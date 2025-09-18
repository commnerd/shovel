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

Route::get('/dashboard/projects/create', [App\Http\Controllers\ProjectsController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('projects.create');

Route::post('/dashboard/projects/create/tasks', [App\Http\Controllers\ProjectsController::class, 'createTasksPage'])
    ->middleware(['auth', 'verified'])
    ->name('projects.create-tasks');

Route::post('/dashboard/projects', [App\Http\Controllers\ProjectsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.store');

Route::get('/dashboard/projects/{project}/edit', [App\Http\Controllers\ProjectsController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('projects.edit');

Route::put('/dashboard/projects/{project}', [App\Http\Controllers\ProjectsController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('projects.update');

Route::get('/dashboard/projects/{project}/tasks', [App\Http\Controllers\TasksController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.index');

Route::get('/dashboard/projects/{project}/tasks/create', [App\Http\Controllers\TasksController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.create');

Route::post('/dashboard/projects/{project}/tasks', [App\Http\Controllers\TasksController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.store');

Route::get('/dashboard/projects/{project}/tasks/{task}/edit', [App\Http\Controllers\TasksController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.edit');

Route::put('/dashboard/projects/{project}/tasks/{task}', [App\Http\Controllers\TasksController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.update');

Route::delete('/dashboard/projects/{project}/tasks/{task}', [App\Http\Controllers\TasksController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.destroy');

Route::delete('/dashboard/projects/{project}', [App\Http\Controllers\ProjectsController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projects.destroy');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
