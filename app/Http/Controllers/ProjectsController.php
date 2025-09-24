<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AI\Facades\AI;
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
            $allProjects = \App\Models\Project::where(function ($query) use ($userGroupIds) {
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
                        'project_type' => $project->project_type,
                        'created_at' => $project->created_at->toISOString(),
                        'tasks_count' => $project->tasks_count,
                    ];
                });

            // Separate projects by type
            $iterativeProjects = $allProjects->where('project_type', 'iterative')->values();
            $finiteProjects = $allProjects->where('project_type', 'finite')->values();

            // If no projects exist, redirect to create page
            if ($allProjects->isEmpty()) {
                return redirect()->route('projects.create');
            }

            return Inertia::render('Projects/Index', [
                'projects' => $allProjects, // Backward compatibility
                'iterativeProjects' => $iterativeProjects,
                'finiteProjects' => $finiteProjects,
            ]);
        } catch (\Exception $e) {
            // If database tables don't exist, redirect to create page
            \Log::warning('Projects table may not exist: '.$e->getMessage());

            return redirect()->route('projects.create');
        }
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(Request $request)
    {
        // Ensure we have a fresh user object from the database
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            // Refresh user from database to ensure we have latest data
            $user = \App\Models\User::find(auth()->id());
            if ($user) {
                auth()->setUser($user);
            }
        }

        // Get user's available groups (within their organization)
        $userGroups = $user->getOrganizationGroups()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_default' => $group->is_default,
                'organization_name' => $group->organization->name,
            ];
        });

        $defaultGroup = $userGroups->where('is_default', true)->first();

        // Get AI configuration data
        $orgId = $user->organization?->id;

        // Get default AI settings (system-wide or organization-specific)
        $defaultAISettings = [
            'provider' => \App\Models\Setting::get('ai.default.provider', 'cerebras'),
            'model' => \App\Models\Setting::get('ai.default.model', ''),
        ];

        // Override with organization-specific settings if available
        if ($orgId) {
            $orgProvider = \App\Models\Setting::get("ai.organization.{$orgId}.provider");
            $orgModel = \App\Models\Setting::get("ai.organization.{$orgId}.model");

            if ($orgProvider) {
                $defaultAISettings['provider'] = $orgProvider;
                $defaultAISettings['model'] = $orgModel ?? '';
            }
        }

        // Get available providers and models (centralized configuration)
        $availableProviders = \App\Services\AIConfigurationService::getAvailableProviders();

        // Pre-populate form data if coming from task generation page
        $formData = [
            'title' => $request->input('title', ''),
            'description' => $request->input('description', ''),
            'due_date' => $request->input('due_date', ''),
            'group_id' => $request->input('group_id', $defaultGroup['id'] ?? null),
            'ai_provider' => $request->input('ai_provider', $defaultAISettings['provider']),
            'ai_model' => $request->input('ai_model', $defaultAISettings['model']),
        ];

        return Inertia::render('Projects/Create', [
            'userGroups' => $userGroups,
            'defaultGroupId' => $defaultGroup['id'] ?? null,
            'defaultAISettings' => $defaultAISettings,
            'availableProviders' => $availableProviders,
            'formData' => $formData,
        ]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project)
    {
        // Check if user can access this project
        if (! $this->canAccessProject($project)) {
            abort(403, 'You do not have permission to access this project.');
        }

        // Get available AI providers for the dropdown (centralized configuration)
        $availableProviders = \App\Services\AIConfigurationService::getAvailableProviders();

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'status' => $project->status,
                'ai_provider' => $project->ai_provider,
                'ai_model' => $project->ai_model,
            ],
            'availableProviders' => $availableProviders,
        ]);
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project)
    {
        // Check if user can modify this project
        if (! $this->canModifyProject($project)) {
            abort(403, 'You do not have permission to modify this project.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
            'status' => 'sometimes|in:active,completed,archived',
            'ai_provider' => 'nullable|in:cerebras,openai,anthropic',
            'ai_model' => 'nullable|string|max:100',
        ]);

        $project->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'] ?? $project->status,
            'ai_provider' => $validated['ai_provider'] ?? null,
            'ai_model' => $validated['ai_model'] ?? null,
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
        \Log::info("Delete request received for project {$project->id} by user ".auth()->id());

        // Check if user can modify this project
        if (! $this->canModifyProject($project)) {
            \Log::warning("Unauthorized delete attempt for project {$project->id} by user ".auth()->id());
            abort(403, 'You do not have permission to delete this project.');
        }

        try {
            $projectId = $project->id;
            $deleted = $project->delete();

            if ($deleted) {
                \Log::info("Project {$projectId} deleted successfully by user ".auth()->id());

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
                \Log::error("Failed to delete project {$projectId} for user ".auth()->id());

                return back()->withErrors(['error' => 'Failed to delete project. Please try again.']);
            }
        } catch (\Exception $e) {
            \Log::error('Exception during project deletion: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return back()->withErrors(['error' => 'An error occurred while deleting the project: '.$e->getMessage()]);
        }
    }

    /**
     * Show the task generation page (GET request).
     */
    public function showCreateTasksPage(Request $request)
    {
        // Ensure we have a fresh user object from the database
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            // Refresh user from database to ensure we have latest data
            $user = \App\Models\User::find(auth()->id());
            if ($user) {
                auth()->setUser($user);
            }
        }

        // Get user's groups for the dropdown
        $userGroups = $user->getOrganizationGroups()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_default' => $group->is_default,
                'organization_name' => $group->organization->name ?? 'Unknown',
            ];
        });

        $defaultGroup = $userGroups->where('is_default', true)->first();

        return Inertia::render('Projects/CreateTasks', [
            'projectData' => [
                'title' => $request->get('title'),
                'description' => $request->get('description', ''),
                'due_date' => $request->get('due_date'),
                'group_id' => $request->get('group_id', $defaultGroup['id'] ?? null),
                'ai_provider' => $request->get('ai_provider'),
                'ai_model' => $request->get('ai_model'),
            ],
            'suggestedTasks' => [],
            'aiUsed' => false,
            'aiCommunication' => null,
            'userGroups' => $userGroups,
            'defaultGroupId' => $defaultGroup['id'] ?? null,
        ]);
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
            'user_feedback' => 'nullable|string|max:2000',
            'ai_provider' => 'nullable|string|in:cerebras,openai,anthropic',
            'ai_model' => 'nullable|string|max:100',
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
                        'status' => 'pending|in_progress|completed',
                        'due_date' => 'date (optional)',
                        'size' => 'xs|s|m|l|xl (optional, for iterative projects)',
                        'initial_story_points' => 'integer 1-100 (optional, for iterative projects)',
                        'current_story_points' => 'integer 1-100 (optional, for iterative projects)',
                        'story_points_change_count' => 'integer 0+ (optional, defaults to 0)',
                        'subtasks' => [],
                    ],
                ],
                'summary' => 'string (optional)',
                'notes' => ['array of strings (optional)'],
                'problems' => ['array of strings (optional)'],
                'suggestions' => ['array of strings (optional)'],
            ];

            $aiOptions = [];
            if (! empty($validated['user_feedback'])) {
                $aiOptions['user_feedback'] = $validated['user_feedback'];
            }

            // Use selected AI provider and model
            if (!empty($validated['ai_provider'])) {
                $aiOptions['provider'] = $validated['ai_provider'];
            }
            if (!empty($validated['ai_model'])) {
                $aiOptions['model'] = $validated['ai_model'];
            }

            // Add project due date to AI options for due date calculation
            if (!empty($validated['due_date'])) {
                $aiOptions['project_due_date'] = $validated['due_date'];
            }

            // Use specific provider if selected
            if (!empty($validated['ai_provider'])) {
                $aiResponse = AI::driver($validated['ai_provider'])->generateTasks($validated['description'], $taskSchema, $aiOptions);
            } else {
                $aiResponse = AI::generateTasks($validated['description'], $taskSchema, $aiOptions);
            }
            $aiUsed = $aiResponse->isSuccessful();

            if ($aiResponse->isSuccessful()) {
                // Transform AI tasks to match our expected format
                $suggestedTasks = collect($aiResponse->getTasks())->map(function ($task, $index) {
                    return [
                        'title' => $task['title'] ?? 'Generated Task',
                        'description' => $task['description'] ?? '',
                        'status' => $task['status'] ?? 'pending',
                        'due_date' => $task['due_date'] ?? null,
                        'sort_order' => $index + 1,
                        'size' => $task['size'] ?? null,
                        'initial_story_points' => $task['initial_story_points'] ?? null,
                        'current_story_points' => $task['current_story_points'] ?? null,
                        'story_points_change_count' => $task['story_points_change_count'] ?? 0,
                    ];
                })->toArray();

                // Get AI-generated title if user didn't provide one
                if (empty($suggestedTitle) && $aiResponse->getProjectTitle()) {
                    $suggestedTitle = $aiResponse->getProjectTitle();
                }

                // If still no title, generate a simple one from description
                if (empty($suggestedTitle)) {
                    $words = str_word_count($validated['description'], 1);
                    $suggestedTitle = implode(' ', array_slice($words, 0, 4)) . ' Project';
                }

                // Get AI communication
                $aiCommunication = $aiResponse->getCommunication();

                \Log::info('AI task generation successful', [
                    'task_count' => count($suggestedTasks),
                    'has_notes' => $aiResponse->hasNotes(),
                    'generated_title' => $suggestedTitle,
                ]);
            } else {
                throw new \Exception($aiResponse->getError() ?? 'AI task generation failed');
            }

        } catch (\Exception $e) {
            \Log::error('AI task generation failed: '.$e->getMessage());

            // Fallback to static tasks if AI fails
            $suggestedTasks = [
                [
                    'title' => 'Project Setup & Planning',
                    'description' => 'Set up project structure and define requirements based on: '.$validated['description'],
                    'status' => 'pending',
                    'sort_order' => 1,
                    'size' => 'm',
                    'initial_story_points' => null,
                    'current_story_points' => null,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Design System Creation',
                    'description' => 'Create design system and component library',
                    'status' => 'pending',
                    'sort_order' => 2,
                    'size' => 'l',
                    'initial_story_points' => null,
                    'current_story_points' => null,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Core Feature Development',
                    'description' => 'Implement main functionality based on project description',
                    'status' => 'pending',
                    'sort_order' => 3,
                    'size' => 'xl',
                    'initial_story_points' => null,
                    'current_story_points' => null,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Testing & Quality Assurance',
                    'description' => 'Write tests and ensure code quality',
                    'status' => 'pending',
                    'sort_order' => 4,
                    'size' => 'm',
                    'initial_story_points' => null,
                    'current_story_points' => null,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Documentation & Deployment',
                    'description' => 'Create documentation and deploy the project',
                    'status' => 'pending',
                    'sort_order' => 5,
                    'size' => 's',
                    'initial_story_points' => null,
                    'current_story_points' => null,
                    'story_points_change_count' => 0,
                ],
            ];

            // Generate a fallback title if none provided
            if (empty($suggestedTitle)) {
                $words = str_word_count($validated['description'], 1);
                $suggestedTitle = implode(' ', array_slice($words, 0, 4)) . ' Project';
            }
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
                'title' => $suggestedTitle ?? '', // Use empty string instead of null
                'description' => $validated['description'],
                'due_date' => $validated['due_date'] ?? null,
                'group_id' => $validated['group_id'] ?? ($defaultGroup['id'] ?? null),
                'ai_provider' => $validated['ai_provider'] ?? null,
                'ai_model' => $validated['ai_model'] ?? null,
                'project_type' => $validated['project_type'] ?? 'iterative',
                'default_iteration_length_weeks' => $validated['default_iteration_length_weeks'] ?? null,
                'auto_create_iterations' => $validated['auto_create_iterations'] ?? false,
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
        // Ensure we have a fresh user object from the database
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            // Refresh user from database to ensure we have latest data
            $user = \App\Models\User::find(auth()->id());
            if ($user) {
                auth()->setUser($user);
            }
        }

        // Get configured providers for validation
        $availableProviders = \App\Services\AIConfigurationService::getAvailableProviders();
        $configuredProviderKeys = array_keys($availableProviders);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
            'group_id' => 'nullable|exists:groups,id',
            'project_type' => 'nullable|string|in:finite,iterative',
            'default_iteration_length_weeks' => 'nullable|integer|min:1|max:12',
            'auto_create_iterations' => 'nullable|boolean',
            'ai_provider' => $configuredProviderKeys ? 'nullable|string|in:' . implode(',', $configuredProviderKeys) : 'nullable|string',
            'ai_model' => 'nullable|string|max:100',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string|max:1000',
            'tasks.*.status' => 'nullable|string|in:pending,in_progress,completed',
            'tasks.*.due_date' => 'nullable|date|after_or_equal:today',
            'tasks.*.sort_order' => 'nullable|integer|min:1',
            'tasks.*.size' => 'nullable|string|in:xs,s,m,l,xl',
            'tasks.*.initial_story_points' => 'nullable|integer|min:1|max:100',
            'tasks.*.current_story_points' => 'nullable|integer|min:1|max:100',
            'tasks.*.story_points_change_count' => 'nullable|integer|min:0',
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
                    $projectTitle = implode(' ', array_slice($words, 0, 3)).' Project';
                    \Log::info('Using fallback project title', ['title' => $projectTitle]);
                }
            }

            // Determine group assignment
            $groupId = $validated['group_id'];
            if (! $groupId) {
                // Default to user's default group
                $defaultGroup = auth()->user()->getDefaultGroup();
                $groupId = $defaultGroup?->id;

                // If user has no default group, we need to handle this case
                if (!$groupId) {
                    throw new \Exception('No group specified and user has no default group available.');
                }
            }

            // Ensure group_id is an integer
            $groupId = (int) $groupId;

            // Verify user has access to the selected group
            if ($groupId && ! auth()->user()->belongsToGroup($groupId)) {
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
                'project_type' => $validated['project_type'] ?? 'iterative',
                'default_iteration_length_weeks' => $validated['default_iteration_length_weeks'] ?? null,
                'auto_create_iterations' => $validated['auto_create_iterations'] ?? false,
                'ai_provider' => $validated['ai_provider'] ?? null,
                'ai_model' => $validated['ai_model'] ?? null,
            ]);

            // Apply default AI configuration if not explicitly set and providers are available
            if (empty($validated['ai_provider']) && !empty($configuredProviderKeys)) {
                $project->applyDefaultAIConfiguration();
            }

            // Create first iteration for iterative projects if auto-creation is enabled
            if ($project->isIterative() && $project->auto_create_iterations) {
                $project->createNextIterationIfNeeded();
            }

            // Create tasks if provided
            if (! empty($validated['tasks'])) {
                foreach ($validated['tasks'] as $taskData) {
                    $project->tasks()->create([
                        'title' => $taskData['title'],
                        'description' => $taskData['description'] ?? '',
                        'status' => $taskData['status'] ?? 'pending',
                        'due_date' => $taskData['due_date'] ?? null,
                        'sort_order' => $taskData['sort_order'] ?? 1,
                        'size' => $taskData['size'] ?? null,
                        'initial_story_points' => $taskData['initial_story_points'] ?? null,
                        'current_story_points' => $taskData['current_story_points'] ?? null,
                        'story_points_change_count' => $taskData['story_points_change_count'] ?? 0,
                    ]);
                }
            }

            \DB::commit();

            \Log::info("Project {$project->id} created successfully with ".count($validated['tasks'] ?? []).' tasks');

            return redirect()->route('projects.index')->with([
                'message' => 'Project created successfully with '.count($validated['tasks'] ?? []).' tasks!',
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Project creation failed: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'user_org_id' => auth()->user()->organization_id ?? 'null',
                'request_data' => $request->all()
            ]);

            return back()->withErrors(['error' => 'Failed to create project: ' . $e->getMessage()]);
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
