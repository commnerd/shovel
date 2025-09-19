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

Route::get('/dashboard/projects/{project}/tasks/{task}/subtasks/create', [App\Http\Controllers\TasksController::class, 'createSubtask'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.subtasks.create');

Route::get('/dashboard/projects/{project}/tasks/{task}/breakdown', [App\Http\Controllers\TasksController::class, 'showBreakdown'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.breakdown');

Route::post('/dashboard/projects/{project}/tasks', [App\Http\Controllers\TasksController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.store');

Route::post('/dashboard/projects/{project}/tasks/breakdown', [App\Http\Controllers\TasksController::class, 'generateTaskBreakdown'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.breakdown');

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

// Admin routes
Route::middleware(['auth', 'verified', App\Http\Middleware\EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
    Route::get('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])
        ->name('admin.users.index');

    Route::post('/users/{user}/approve', [App\Http\Controllers\Admin\UserManagementController::class, 'approve'])
        ->name('admin.users.approve');

    Route::post('/users/{user}/assign-role', [App\Http\Controllers\Admin\UserManagementController::class, 'assignRole'])
        ->name('admin.users.assign-role');

    Route::delete('/users/{user}/remove-role', [App\Http\Controllers\Admin\UserManagementController::class, 'removeRole'])
        ->name('admin.users.remove-role');

    Route::post('/users/{user}/add-to-group', [App\Http\Controllers\Admin\UserManagementController::class, 'addToGroup'])
        ->name('admin.users.add-to-group');

    Route::delete('/users/{user}/remove-from-group', [App\Http\Controllers\Admin\UserManagementController::class, 'removeFromGroup'])
        ->name('admin.users.remove-from-group');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
