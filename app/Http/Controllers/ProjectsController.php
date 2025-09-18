<?php

namespace App\Http\Controllers;

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
        $projects = auth()->user()
            ->projects()
            ->latest()
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'user_id' => $project->user_id, // Include for tests
                    'description' => $project->description,
                    'due_date' => $project->due_date?->format('Y-m-d'),
                    'status' => $project->status,
                    'created_at' => $project->created_at->toISOString(),
                    'tasks' => [], // Mock empty tasks for now
                ];
            });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        // Create the project in the database
        $project = Project::create([
            'user_id' => auth()->id(),
            'description' => $validated['description'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'active',
        ]);

        // Mock AI response with initial task layout
        $mockTasks = [
            [
                'id' => 1,
                'title' => 'Project Setup & Planning',
                'description' => 'Set up project structure and define requirements',
                'status' => 'pending',
                'priority' => 'high',
            ],
            [
                'id' => 2,
                'title' => 'Design System Creation',
                'description' => 'Create design system and component library',
                'status' => 'pending',
                'priority' => 'medium',
            ],
            [
                'id' => 3,
                'title' => 'Core Feature Development',
                'description' => 'Implement main functionality based on project description',
                'status' => 'pending',
                'priority' => 'high',
            ],
            [
                'id' => 4,
                'title' => 'Testing & Quality Assurance',
                'description' => 'Write tests and ensure code quality',
                'status' => 'pending',
                'priority' => 'medium',
            ],
            [
                'id' => 5,
                'title' => 'Documentation & Deployment',
                'description' => 'Create documentation and deploy the project',
                'status' => 'pending',
                'priority' => 'low',
            ],
        ];

        return redirect()->route('projects.index')->with([
            'message' => 'Project created successfully!',
            'project' => [
                'id' => $project->id,
                'description' => $project->description,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'tasks' => $mockTasks,
                'created_at' => $project->created_at->toISOString(),
            ]
        ]);
    }
}
