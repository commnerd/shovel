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
            $projects = auth()->user()
                ->projects()
                ->latest()
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'user_id' => $project->user_id, // Include for tests
                        'title' => $project->title,
                        'description' => $project->description,
                        'due_date' => $project->due_date?->format('Y-m-d'),
                        'status' => $project->status,
                        'created_at' => $project->created_at->toISOString(),
                        'tasks' => [], // Mock empty tasks for now
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
        return Inertia::render('Projects/Create');
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project)
    {
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
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
        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this project.');
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

        // Ensure the project belongs to the authenticated user
        if ($project->user_id !== auth()->id()) {
            \Log::warning("Unauthorized delete attempt for project {$project->id} by user " . auth()->id());
            abort(403, 'Unauthorized access to this project.');
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

        return Inertia::render('Projects/CreateTasks', [
            'projectData' => [
                'title' => $suggestedTitle,
                'description' => $validated['description'],
                'due_date' => $validated['due_date'],
            ],
            'suggestedTasks' => $suggestedTasks,
            'aiUsed' => $aiUsed,
            'aiCommunication' => $aiCommunication,
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

            // Create the project in the database
            $project = Project::create([
                'user_id' => auth()->id(),
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
}
