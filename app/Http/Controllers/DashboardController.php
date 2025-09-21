<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\WaitlistSubscriber;
use App\Services\AI\AIUsageService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index()
    {
        $user = auth()->user();
        $waitlistCount = WaitlistSubscriber::count();

        // Project metrics
        $totalProjects = $user->projects()->count();
        $activeProjects = $user->projects()->active()->count();
        $completedProjects = $user->projects()->completed()->count();
        $overdueProjects = $user->projects()
            ->where('due_date', '<', today())
            ->where('status', '!=', 'completed')
            ->count();

        // Leaf task metrics (tasks without children)
        $totalLeafTasks = Task::whereHas('project', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->leaf()->count();

        $completedLeafTasks = Task::whereHas('project', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->leaf()->where('status', 'completed')->count();

        $pendingLeafTasks = Task::whereHas('project', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->leaf()->where('status', 'pending')->count();

        $inProgressLeafTasks = Task::whereHas('project', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->leaf()->where('status', 'in_progress')->count();

        // Priority tracking removed - using total leaf tasks instead
        $totalLeafTasks = Task::whereHas('project', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->leaf()->count();

        // Get AI usage metrics for super admins
        $aiUsageMetrics = null;
        if ($user->isSuperAdmin()) {
            try {
                $aiUsageService = new AIUsageService();
                $aiUsageMetrics = $aiUsageService->getUsageMetrics();
            } catch (\Exception $e) {
                // Log error but don't fail the dashboard
                \Log::error('Failed to fetch AI usage metrics for dashboard', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                $aiUsageMetrics = [
                    'status' => 'error',
                    'error' => 'Unable to load AI usage metrics',
                    'last_updated' => now()->toISOString(),
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'waitlistCount' => $waitlistCount,
            'projectMetrics' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $completedProjects,
                'overdue' => $overdueProjects,
            ],
            'taskMetrics' => [
                'totalLeaf' => $totalLeafTasks,
                'completed' => $completedLeafTasks,
                'pending' => $pendingLeafTasks,
                'inProgress' => $inProgressLeafTasks,
                'highPriority' => $totalLeafTasks, // Priority removed - showing total instead
            ],
            'aiUsageMetrics' => $aiUsageMetrics,
        ]);
    }
}
