<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\Providers\CerebrasProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIPromptHierarchicalConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\OrganizationSeeder::class);

        $this->organization = Organization::getDefault();
        $group = $this->organization->createDefaultGroup();

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'pending_approval' => false,
        ]);
        $this->user->joinGroup($group);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'group_id' => $group->id,
            'title' => 'Test Project for AI Prompt Constraints',
            'ai_provider' => 'cerebras',
        ]);
    }

    public function test_cerebras_provider_prompt_includes_size_constraint_for_medium_task()
    {
        $provider = new CerebrasProvider([]);

        // Create a parent task with size 'm'
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Medium Parent Task',
            'size' => 'm',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'due_date' => null,
                'status' => 'active',
            ],
            'parent_task' => [
                'title' => $parentTask->title,
                'size' => $parentTask->size,
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 1,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 1,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt includes the enhanced size constraint
        $this->assertStringContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringContainsString("parent task has a T-shirt size of 'm'", $prompt);
        $this->assertStringContainsString('ABSOLUTE RULE: NO subtask can have 5 or more story points', $prompt);
        $this->assertStringContainsString('MAXIMUM ALLOWED: 4 story points per subtask', $prompt);
        $this->assertStringContainsString('VALID STORY POINTS FOR SUBTASKS: 1, 2, 3', $prompt);
        $this->assertStringContainsString('VIOLATION OF THIS RULE WILL RESULT IN REJECTION', $prompt);
    }

    public function test_cerebras_provider_prompt_includes_size_constraint_for_large_task()
    {
        $provider = new CerebrasProvider([]);

        // Create a parent task with size 'l'
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Large Parent Task',
            'size' => 'l',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'due_date' => null,
                'status' => 'active',
            ],
            'parent_task' => [
                'title' => $parentTask->title,
                'size' => $parentTask->size,
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 1,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 1,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt includes the enhanced size constraint
        $this->assertStringContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringContainsString("parent task has a T-shirt size of 'l'", $prompt);
        $this->assertStringContainsString('ABSOLUTE RULE: NO subtask can have 8 or more story points', $prompt);
        $this->assertStringContainsString('MAXIMUM ALLOWED: 7 story points per subtask', $prompt);
        $this->assertStringContainsString('VALID STORY POINTS FOR SUBTASKS: 1, 2, 3, 5', $prompt);
    }

    public function test_cerebras_provider_prompt_does_not_include_constraint_without_parent_task()
    {
        $provider = new CerebrasProvider([]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'due_date' => null,
                'status' => 'active',
            ],
            'parent_task' => null,
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 0,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt does not include the size constraint
        $this->assertStringNotContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringNotContainsString('parent task has a T-shirt size', $prompt);
        $this->assertStringNotContainsString('ABSOLUTE RULE: NO subtask can have', $prompt);
    }

    public function test_cerebras_provider_prompt_does_not_include_constraint_without_parent_size()
    {
        $provider = new CerebrasProvider([]);

        // Create a parent task without size
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Parent Task without Size',
            'size' => null,
            'parent_id' => null,
            'depth' => 0,
        ]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'due_date' => null,
                'status' => 'active',
            ],
            'parent_task' => [
                'title' => $parentTask->title,
                'size' => null,
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 1,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 1,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt does not include the size constraint
        $this->assertStringNotContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringNotContainsString('parent task has a T-shirt size', $prompt);
        $this->assertStringNotContainsString('ABSOLUTE RULE: NO subtask can have', $prompt);
    }

    public function test_openai_provider_prompt_includes_size_constraint_for_extra_small_task()
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key']);

        // Create a parent task with size 'xs'
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Extra Small Parent Task',
            'size' => 'xs',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
            ],
            'parent_task' => [
                'title' => $parentTask->title,
                'size' => $parentTask->size,
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 1,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 1,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt includes the enhanced size constraint
        $this->assertStringContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringContainsString("parent task has a T-shirt size of 'xs'", $prompt);
        $this->assertStringContainsString('ABSOLUTE RULE: NO subtask can have 2 or more story points', $prompt);
        $this->assertStringContainsString('MAXIMUM ALLOWED: 1 story points per subtask', $prompt);
        $this->assertStringContainsString('VALID STORY POINTS FOR SUBTASKS: 1', $prompt);
    }

    public function test_openai_provider_prompt_includes_size_constraint_for_extra_large_task()
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key']);

        // Create a parent task with size 'xl'
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Extra Large Parent Task',
            'size' => 'xl',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $context = [
            'project' => [
                'title' => $this->project->title,
                'description' => $this->project->description,
            ],
            'parent_task' => [
                'title' => $parentTask->title,
                'size' => $parentTask->size,
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 1,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 1,
            ],
            'user_feedback' => null,
        ];

        // Use reflection to access the protected promptService property
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($provider);

        $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

        // Assert that the prompt includes the enhanced size constraint
        $this->assertStringContainsString('🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨', $prompt);
        $this->assertStringContainsString("parent task has a T-shirt size of 'xl'", $prompt);
        $this->assertStringContainsString('ABSOLUTE RULE: NO subtask can have 13 or more story points', $prompt);
        $this->assertStringContainsString('MAXIMUM ALLOWED: 12 story points per subtask', $prompt);
        $this->assertStringContainsString('VALID STORY POINTS FOR SUBTASKS: 1, 2, 3, 5, 8', $prompt);
    }

    public function test_all_size_constraints_are_correctly_mapped()
    {
        $sizes = ['xs', 's', 'm', 'l', 'xl'];
        $expectedMaxPoints = [2, 3, 5, 8, 13];

        foreach ($sizes as $index => $size) {
            $provider = new CerebrasProvider([]);

            // Create a parent task with the current size
            $parentTask = Task::factory()->create([
                'project_id' => $this->project->id,
                'title' => "Parent Task with {$size} size",
                'size' => $size,
                'parent_id' => null,
                'depth' => 0,
            ]);

            $context = [
                'project' => [
                    'title' => $this->project->title,
                    'description' => $this->project->description,
                    'due_date' => null,
                    'status' => 'active',
                ],
                'parent_task' => [
                    'title' => $parentTask->title,
                    'size' => $parentTask->size,
                ],
                'existing_tasks' => [],
                'task_stats' => [
                    'total' => 1,
                    'completed' => 0,
                    'in_progress' => 0,
                    'pending' => 1,
                ],
                'user_feedback' => null,
            ];

            // Use reflection to access the protected promptService property
            $reflection = new \ReflectionClass($provider);
            $property = $reflection->getProperty('promptService');
            $property->setAccessible(true);
            $promptService = $property->getValue($provider);

            $prompt = $promptService->buildTaskBreakdownUserPrompt('Test Task', 'Test description', $context);

            // Assert that the prompt includes the correct constraint
            $this->assertStringContainsString("parent task has a T-shirt size of '{$size}'", $prompt);
            $this->assertStringContainsString("ABSOLUTE RULE: NO subtask can have {$expectedMaxPoints[$index]} or more story points", $prompt);
            $this->assertStringContainsString("MAXIMUM ALLOWED: " . ($expectedMaxPoints[$index] - 1) . " story points per subtask", $prompt);
        }
    }
}
