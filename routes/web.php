<?php

use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// System Settings routes
Route::get('/settings/system', [App\Http\Controllers\Settings\SettingsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('settings.system.index');

Route::post('/settings/ai', [App\Http\Controllers\Settings\SettingsController::class, 'updateAI'])
    ->middleware(['auth', 'verified'])
    ->name('settings.ai.update');

Route::post('/settings/ai/test', [App\Http\Controllers\Settings\SettingsController::class, 'testAI'])
    ->middleware(['auth', 'verified'])
    ->name('settings.ai.test');

Route::post('/settings/ai/default', [App\Http\Controllers\Settings\SettingsController::class, 'updateDefaultAI'])
    ->middleware(['auth', 'verified'])
    ->name('settings.ai.default');

Route::post('/settings/ai/organization', [App\Http\Controllers\Settings\SettingsController::class, 'updateOrganizationAI'])
    ->middleware(['auth', 'verified'])
    ->name('settings.ai.organization');

// Super Admin Return Route (outside middleware to allow impersonated users to return)
Route::post('/super-admin/return-to-super-admin', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'returnToSuperAdmin'])
    ->middleware(['auth', 'verified'])
    ->name('super-admin.return');

// Admin Return Route (outside middleware to allow impersonated users to return)
Route::post('/admin/return-to-admin', [App\Http\Controllers\Admin\UserManagementController::class, 'returnToAdmin'])
    ->middleware(['auth', 'verified'])
    ->name('admin.return');

// Super Admin routes
Route::middleware(['auth', 'verified', App\Http\Middleware\EnsureUserIsSuperAdmin::class])->prefix('super-admin')->group(function () {
    Route::get('/', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'index'])->name('super-admin.index');
    Route::get('/users', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'users'])->name('super-admin.users');
    Route::get('/organizations', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'organizations'])->name('super-admin.organizations');

    // User search endpoint
    Route::get('/users/search', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'searchUsers'])->name('super-admin.users.search');

    Route::post('/users/{user}/login-as', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'loginAsUser'])->name('super-admin.login-as');

    Route::post('/users/{user}/assign-super-admin', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'assignSuperAdmin'])->name('super-admin.assign');
    Route::post('/users/{user}/remove-super-admin', [App\Http\Controllers\SuperAdmin\SuperAdminController::class, 'removeSuperAdmin'])->name('super-admin.remove');
});

// Organization Admin routes
Route::middleware(['auth', 'verified', App\Http\Middleware\EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
    Route::get('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('admin.users');

    // User search endpoint
    Route::get('/users/search', [App\Http\Controllers\Admin\UserManagementController::class, 'searchUsers'])->name('admin.users.search');

    Route::post('/users/{user}/login-as', [App\Http\Controllers\Admin\UserManagementController::class, 'loginAsUser'])->name('admin.login-as');
});

Route::get('/dashboard/projects', [App\Http\Controllers\ProjectsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.index');

Route::get('/dashboard/projects/create', [App\Http\Controllers\ProjectsController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('projects.create');

Route::get('/dashboard/projects/create/tasks', [App\Http\Controllers\ProjectsController::class, 'showCreateTasksPage'])
    ->middleware(['auth', 'verified'])
    ->name('projects.create-tasks.show');

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
    ->name('projects.tasks.show_breakdown');

Route::get('/dashboard/projects/{project}/tasks/{task}/subtasks/reorder', [App\Http\Controllers\TasksController::class, 'showSubtaskReorder'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.subtasks.reorder');

Route::post('/dashboard/projects/{project}/tasks', [App\Http\Controllers\TasksController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.store');

Route::post('/dashboard/projects/{project}/tasks/breakdown', [App\Http\Controllers\TasksController::class, 'generateTaskBreakdown'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.breakdown');

Route::post('/dashboard/projects/{project}/tasks/subtasks', [App\Http\Controllers\TasksController::class, 'saveSubtasks'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.subtasks.save');

Route::get('/dashboard/projects/{project}/tasks/{task}/edit', [App\Http\Controllers\TasksController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.edit');

Route::put('/dashboard/projects/{project}/tasks/{task}', [App\Http\Controllers\TasksController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.update');

Route::delete('/dashboard/projects/{project}/tasks/{task}', [App\Http\Controllers\TasksController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.destroy');

Route::post('/dashboard/projects/{project}/tasks/{task}/reorder', [App\Http\Controllers\TasksController::class, 'reorder'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.reorder');

Route::patch('/dashboard/projects/{project}/tasks/{task}/status', [App\Http\Controllers\TasksController::class, 'updateStatus'])
    ->middleware(['auth', 'verified'])
    ->name('projects.tasks.update-status');

Route::patch('/dashboard/tasks/{task}', [App\Http\Controllers\TasksController::class, 'updateSizing'])
    ->middleware(['auth', 'verified'])
    ->name('tasks.update');

// Today's Tasks routes
Route::get('/dashboard/todays-tasks', [App\Http\Controllers\TodaysTasksController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.index');

Route::post('/dashboard/todays-tasks/refresh', [App\Http\Controllers\TodaysTasksController::class, 'refresh'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.refresh');

Route::post('/dashboard/todays-tasks/curations/{curation}/dismiss', [App\Http\Controllers\TodaysTasksController::class, 'dismiss'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.curations.dismiss');

Route::post('/dashboard/todays-tasks/tasks/{task}/complete', [App\Http\Controllers\TodaysTasksController::class, 'completeTask'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.tasks.complete');

Route::patch('/dashboard/todays-tasks/tasks/{task}/status', [App\Http\Controllers\TodaysTasksController::class, 'updateTaskStatus'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.tasks.update-status');

Route::get('/dashboard/todays-tasks/stats', [App\Http\Controllers\TodaysTasksController::class, 'stats'])
    ->middleware(['auth', 'verified'])
    ->name('todays-tasks.stats');

Route::delete('/dashboard/projects/{project}', [App\Http\Controllers\ProjectsController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projects.destroy');

// Iteration routes
Route::get('/dashboard/projects/{project}/iterations', [App\Http\Controllers\IterationsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.index');

Route::get('/dashboard/projects/{project}/iterations/create', [App\Http\Controllers\IterationsController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.create');

Route::post('/dashboard/projects/{project}/iterations', [App\Http\Controllers\IterationsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.store');

Route::get('/dashboard/projects/{project}/iterations/{iteration}', [App\Http\Controllers\IterationsController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.show');

Route::get('/dashboard/projects/{project}/iterations/{iteration}/edit', [App\Http\Controllers\IterationsController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.edit');

Route::put('/dashboard/projects/{project}/iterations/{iteration}', [App\Http\Controllers\IterationsController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.update');

Route::delete('/dashboard/projects/{project}/iterations/{iteration}', [App\Http\Controllers\IterationsController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.destroy');

Route::post('/dashboard/projects/{project}/iterations/{iteration}/tasks', [App\Http\Controllers\IterationsController::class, 'moveTask'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.move-task');

Route::delete('/dashboard/projects/{project}/iterations/{iteration}/tasks', [App\Http\Controllers\IterationsController::class, 'removeTask'])
    ->middleware(['auth', 'verified'])
    ->name('projects.iterations.remove-task');

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

    // User Invitations routes
    Route::get('/invitations', [App\Http\Controllers\UserInvitationController::class, 'index'])
        ->name('admin.invitations.index');

    Route::get('/invitations/create', [App\Http\Controllers\UserInvitationController::class, 'create'])
        ->name('admin.invitations.create');

    Route::post('/invitations', [App\Http\Controllers\UserInvitationController::class, 'store'])
        ->name('admin.invitations.store');

    Route::delete('/invitations/{invitation}', [App\Http\Controllers\UserInvitationController::class, 'destroy'])
        ->name('admin.invitations.destroy');

    Route::post('/invitations/{invitation}/resend', [App\Http\Controllers\UserInvitationController::class, 'resend'])
        ->name('admin.invitations.resend');
});

// Public invitation routes (no auth required)
Route::get('/invitation/{token}', [App\Http\Controllers\Auth\SetPasswordController::class, 'show'])
    ->name('invitation.set-password');

Route::post('/invitation/{token}', [App\Http\Controllers\Auth\SetPasswordController::class, 'store'])
    ->name('invitation.set-password.store');


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
