<?php

namespace App\Http\Controllers;

use App\Services\AI\Facades\AI;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectsController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index()
    {
        try {
            // Get user's group IDs
            $userGroupIds = auth()->user()->groups()->pluck('groups.id');

            // Get projects from user's groups or created by the user
            $projects = \App\Models\Project::where(function ($query) use ($userGroupIds) {
                $query->where('user_id', auth()->id())
                      ->orWhereIn('group_id', $userGroupIds);
            })
                ->with(['group', 'user'])
                ->withCount('tasks')
                ->latest()
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'user_id' => $project->user_id, // Include for tests
                        'group_id' => $project->group_id,
                        'group_name' => $project->group?->name,
                        'title' => $project->title,
                        'description' => $project->description,
                        'due_date' => $project->due_date?->format('Y-m-d'),
                        'status' => $project->status,
                        'created_at' => $project->created_at->toISOString(),
                        'tasks_count' => $project->tasks_count,
                    ];
                });

            // If no projects exist, redirect to create page
            if ($projects->isEmpty()) {
                return redirect()->route('projects.create');
            }

            return Inertia::render('Projects/Index', [
                'projects' => $projects,
            ]);
        } catch (\Exception $e) {
            // If database tables don't exist, redirect to create page
            \Log::warning('Projects table may not exist: ' . $e->getMessage());
            return redirect()->route('projects.create');
        }
    }

    /**
     * Show the form for creating a new project.
     */
    public function create()
    {
        // Get user's available groups (within their organization)
        $userGroups = auth()->user()->getOrganizationGroups()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_default' => $group->is_default,
                'organization_name' => $group->organization->name,
            ];
        });

        $defaultGroup = $userGroups->where('is_default', true)->first();

        return Inertia::render('Projects/Create', [
            'userGroups' => $userGroups,
            'defaultGroupId' => $defaultGroup['id'] ?? null,
        ]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project)
    {
        // Check if user can access this project
        if (!$this->canAccessProject($project)) {
            abort(403, 'You do not have permission to access this project.');
        }

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'status' => $project->status,
            ],
        ]);
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project)
    {
        // Check if user can modify this project
        if (!$this->canModifyProject($project)) {
            abort(403, 'You do not have permission to modify this project.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
            'status' => 'sometimes|in:active,completed,archived',
        ]);

        $project->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'] ?? $project->status,
        ]);

        return redirect()->route('projects.index')->with([
            'message' => 'Project updated successfully!',
        ]);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project)
    {
        \Log::info("Delete request received for project {$project->id} by user " . auth()->id());

        // Check if user can modify this project
        if (!$this->canModifyProject($project)) {
            \Log::warning("Unauthorized delete attempt for project {$project->id} by user " . auth()->id());
            abort(403, 'You do not have permission to delete this project.');
        }

        try {
            $projectId = $project->id;
            $deleted = $project->delete();

            if ($deleted) {
                \Log::info("Project {$projectId} deleted successfully by user " . auth()->id());

                // Check if user has any remaining projects
                $hasProjects = auth()->user()->projects()->exists();

                if ($hasProjects) {
                    return redirect()->route('projects.index')->with([
                        'message' => 'Project deleted successfully!',
                    ]);
                } else {
                    // If no projects left, redirect to create page
                    return redirect()->route('projects.create')->with([
                        'message' => 'Project deleted successfully! Create your next project below.',
                    ]);
                }
            } else {
                \Log::error("Failed to delete project {$projectId} for user " . auth()->id());
                return back()->withErrors(['error' => 'Failed to delete project. Please try again.']);
            }
        } catch (\Exception $e) {
            \Log::error("Exception during project deletion: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return back()->withErrors(['error' => 'An error occurred while deleting the project: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the task generation page with AI-generated tasks.
     */
    public function createTasksPage(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
            'group_id' => 'nullable|exists:groups,id',
            'regenerate' => 'nullable|boolean',
        ]);

        $aiUsed = false;
        $suggestedTasks = [];
        $aiCommunication = null;
        $suggestedTitle = $validated['title'] ?? null;

        try {
            \Log::info('Generating AI task suggestions for task page', ['description' => $validated['description']]);

            // Define the expected task schema
            $taskSchema = [
                'project_title' => 'string',
                'tasks' => [
                    [
                        'title' => 'string',
                        'description' => 'string',
                        'priority' => 'high|medium|low',
                        'status' => 'pending|in_progress|completed',
                        'subtasks' => []
                    ]
                ],
                'summary' => 'string (optional)',
                'notes' => ['array of strings (optional)'],
                'problems' => ['array of strings (optional)'],
                'suggestions' => ['array of strings (optional)']
            ];

            $aiResponse = AI::generateTasks($validated['description'], $taskSchema);
            $aiUsed = $aiResponse->isSuccessful();

            if ($aiResponse->isSuccessful()) {
                // Transform AI tasks to match our expected format
                $suggestedTasks = collect($aiResponse->getTasks())->map(function ($task, $index) {
                    return [
                        'title' => $task['title'] ?? 'Generated Task',
                        'description' => $task['description'] ?? '',
                        'status' => $task['status'] ?? 'pending',
                        'priority' => $task['priority'] ?? 'medium',
                        'sort_order' => $index + 1,
                    ];
                })->toArray();

                // Get AI-generated title if user didn't provide one
                if (empty($suggestedTitle) && $aiResponse->getProjectTitle()) {
                    $suggestedTitle = $aiResponse->getProjectTitle();
                }

                // Get AI communication
                $aiCommunication = $aiResponse->getCommunication();

                \Log::info('AI task generation successful', [
                    'task_count' => count($suggestedTasks),
                    'has_notes' => $aiResponse->hasNotes(),
                    'generated_title' => $suggestedTitle
                ]);
            } else {
                throw new \Exception($aiResponse->getError() ?? 'AI task generation failed');
            }

        } catch (\Exception $e) {
            \Log::error('AI task generation failed: ' . $e->getMessage());

            // Fallback to static tasks if AI fails
            $suggestedTasks = [
                [
                    'title' => 'Project Setup & Planning',
                    'description' => 'Set up project structure and define requirements based on: ' . $validated['description'],
                    'status' => 'pending',
                    'priority' => 'high',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Design System Creation',
                    'description' => 'Create design system and component library',
                    'status' => 'pending',
                    'priority' => 'medium',
                    'sort_order' => 2,
                ],
                [
                    'title' => 'Core Feature Development',
                    'description' => 'Implement main functionality based on project description',
                    'status' => 'pending',
                    'priority' => 'high',
                    'sort_order' => 3,
                ],
                [
                    'title' => 'Testing & Quality Assurance',
                    'description' => 'Write tests and ensure code quality',
                    'status' => 'pending',
                    'priority' => 'medium',
                    'sort_order' => 4,
                ],
                [
                    'title' => 'Documentation & Deployment',
                    'description' => 'Create documentation and deploy the project',
                    'status' => 'pending',
                    'priority' => 'low',
                    'sort_order' => 5,
                ],
            ];
        }

        // Get user's available groups for the form
        $userGroups = auth()->user()->getOrganizationGroups()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_default' => $group->is_default,
                'organization_name' => $group->organization->name,
            ];
        });

        $defaultGroup = $userGroups->where('is_default', true)->first();

        return Inertia::render('Projects/CreateTasks', [
            'projectData' => [
                'title' => $suggestedTitle,
                'description' => $validated['description'],
                'due_date' => $validated['due_date'],
                'group_id' => $validated['group_id'] ?? ($defaultGroup['id'] ?? null),
            ],
            'suggestedTasks' => $suggestedTasks,
            'aiUsed' => $aiUsed,
            'aiCommunication' => $aiCommunication,
            'userGroups' => $userGroups,
            'defaultGroupId' => $defaultGroup['id'] ?? null,
        ]);
    }

    /**
     * Store a newly created project with tasks.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
            'group_id' => 'nullable|exists:groups,id',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string|max:1000',
            'tasks.*.status' => 'nullable|string|in:pending,in_progress,completed',
            'tasks.*.priority' => 'nullable|string|in:low,medium,high',
            'tasks.*.sort_order' => 'nullable|integer|min:1',
        ]);

        try {
            \DB::beginTransaction();

            // Generate title via AI if not provided
            $projectTitle = $validated['title'];
            if (empty($projectTitle)) {
                try {
                    $aiResponse = AI::generateTasks($validated['description'], []);
                    if ($aiResponse->isSuccessful() && $aiResponse->getProjectTitle()) {
                        $projectTitle = $aiResponse->getProjectTitle();
                        \Log::info('AI generated project title', ['title' => $projectTitle]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to generate AI title, using fallback', ['error' => $e->getMessage()]);
                }

                // Generate simple fallback title if AI didn't provide one
                if (empty($projectTitle)) {
                    $words = str_word_count($validated['description'], 1);
                    $projectTitle = implode(' ', array_slice($words, 0, 3)) . ' Project';
                    \Log::info('Using fallback project title', ['title' => $projectTitle]);
                }
            }

            // Determine group assignment
            $groupId = $validated['group_id'];
            if (!$groupId) {
                // Default to user's default group
                $defaultGroup = auth()->user()->getDefaultGroup();
                $groupId = $defaultGroup?->id;
            }

            // Verify user has access to the selected group
            if ($groupId && !auth()->user()->belongsToGroup($groupId)) {
                throw new \Exception('You do not have access to the selected group.');
            }

            // Create the project in the database
            $project = Project::create([
                'user_id' => auth()->id(),
                'group_id' => $groupId,
                'title' => $projectTitle,
                'description' => $validated['description'],
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'active',
            ]);

            // Create tasks if provided
            if (!empty($validated['tasks'])) {
                foreach ($validated['tasks'] as $taskData) {
                    $project->tasks()->create([
                        'title' => $taskData['title'],
                        'description' => $taskData['description'] ?? '',
                        'status' => $taskData['status'] ?? 'pending',
                        'priority' => $taskData['priority'] ?? 'medium',
                        'sort_order' => $taskData['sort_order'] ?? 1,
                    ]);
                }
            }

            \DB::commit();

            \Log::info("Project {$project->id} created successfully with " . count($validated['tasks'] ?? []) . " tasks");

            return redirect()->route('projects.index')->with([
                'message' => 'Project created successfully with ' . count($validated['tasks'] ?? []) . ' tasks!',
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Project creation failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to create project. Please try again.']);
        }
    }

    /**
     * Check if the authenticated user can access the given project.
     */
    private function canAccessProject(Project $project): bool
    {
        $user = auth()->user();

        // User can access project if:
        // 1. They own the project
        // 2. They belong to the same group as the project
        // 3. They are admin and in the same organization

        if ($project->user_id === $user->id) {
            return true;
        }

        if ($project->group && $user->belongsToGroup($project->group_id)) {
            return true;
        }

        // Admin can access all projects in their organization
        if ($user->isAdmin() && $project->group && $project->group->organization_id === $user->organization_id) {
            return true;
        }

        return false;
    }

    /**
     * Check if the authenticated user can modify the given project.
     */
    private function canModifyProject(Project $project): bool
    {
        $user = auth()->user();

        // User can modify project if:
        // 1. They own the project
        // 2. They are admin in the same organization

        if ($project->user_id === $user->id) {
            return true;
        }

        // Admin can modify all projects in their organization
        if ($user->isAdmin() && $project->group && $project->group->organization_id === $user->organization_id) {
            return true;
        }

        return false;
    }
}
