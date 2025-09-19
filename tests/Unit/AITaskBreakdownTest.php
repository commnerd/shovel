<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AI\Providers\CerebrusProvider;
use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Contracts\AIResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AITaskBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.cerebrus.driver' => 'cerebrus',
            'ai.providers.cerebrus.api_key' => 'test-key',
            'ai.providers.cerebrus.base_url' => 'https://api.cerebras.ai/v1',
            'ai.providers.cerebrus.model' => 'test-model',
            'ai.providers.cerebrus.timeout' => 30,
            'ai.providers.cerebrus.max_tokens' => 4000,
            'ai.providers.cerebrus.temperature' => 0.7,
        ]);
    }

    public function test_cerebrus_provider_can_breakdown_task()
    {
        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Mock the HTTP client response
        $mockResponse = Mockery::mock(AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn(json_encode([
            'tasks' => [
                [
                    'title' => 'Setup development environment',
                    'description' => 'Configure tools and dependencies',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2025-12-31',
                ],
                [
                    'title' => 'Implement core functionality',
                    'description' => 'Build the main features',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2026-01-15',
                ],
            ],
            'notes' => ['Task breakdown completed successfully'],
        ]));

        // Mock the chat method
        $provider = Mockery::mock(CerebrusProvider::class)->makePartial();
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn($mockResponse);

        $context = [
            'project' => [
                'title' => 'Test Project',
                'description' => 'A test project',
            ],
            'existing_tasks' => [],
            'task_stats' => [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 0,
            ],
        ];

        $result = $provider->breakdownTask(
            'Implement Authentication',
            'Add user authentication to the system',
            $context
        );

        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertCount(2, $result->getTasks());
        $this->assertContains('Task breakdown completed successfully', $result->getNotes());
    }

    public function test_task_breakdown_prompt_building()
    {
        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        $context = [
            'project' => [
                'title' => 'E-commerce Platform',
                'description' => 'Build online shopping platform',
                'due_date' => '2026-06-30',
                'status' => 'active',
            ],
            'existing_tasks' => [
                [
                    'title' => 'Database Design',
                    'status' => 'completed',
                    'priority' => 'high',
                    'is_leaf' => true,
                    'has_children' => false,
                ],
                [
                    'title' => 'API Development',
                    'status' => 'in_progress',
                    'priority' => 'high',
                    'is_leaf' => false,
                    'has_children' => true,
                ],
            ],
            'task_stats' => [
                'total' => 5,
                'completed' => 2,
                'in_progress' => 2,
                'pending' => 1,
                'leaf_tasks' => 3,
                'parent_tasks' => 2,
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('buildTaskBreakdownUserPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($provider, 'Payment Integration', 'Integrate payment processing', $context);

        // Verify prompt includes all context
        $this->assertStringContainsString('Payment Integration', $prompt);
        $this->assertStringContainsString('Integrate payment processing', $prompt);
        $this->assertStringContainsString('E-commerce Platform', $prompt);
        $this->assertStringContainsString('Build online shopping platform', $prompt);
        $this->assertStringContainsString('2026-06-30', $prompt);
        $this->assertStringContainsString('Database Design (completed)', $prompt);
        $this->assertStringContainsString('API Development (in_progress)', $prompt);
        $this->assertStringContainsString('Total Tasks: 5', $prompt);
        $this->assertStringContainsString('Completed: 2', $prompt);
        $this->assertStringContainsString('In Progress: 2', $prompt);
        $this->assertStringContainsString('Pending: 1', $prompt);
    }

    public function test_task_breakdown_fallback_creation()
    {
        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('createFallbackTaskBreakdown');
        $method->setAccessible(true);

        $result = $method->invoke($provider, 'Complex Feature', 'A complex feature to implement');

        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());

        $tasks = $result->getTasks();
        $this->assertCount(3, $tasks);

        // Verify fallback task structure
        $this->assertEquals('Research & Planning', $tasks[0]['title']);
        $this->assertEquals('Implementation', $tasks[1]['title']);
        $this->assertEquals('Testing & Validation', $tasks[2]['title']);

        foreach ($tasks as $task) {
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('priority', $task);
            $this->assertArrayHasKey('status', $task);
            $this->assertArrayHasKey('due_date', $task);
            $this->assertEquals('pending', $task['status']);
        }

        $this->assertContains('AI task breakdown failed, using fallback subtasks', $result->getNotes());
    }

    public function test_task_breakdown_handles_malformed_ai_response()
    {
        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Mock the chat method to return malformed JSON
        $mockResponse = Mockery::mock(AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn('This is not valid JSON');

        $provider = Mockery::mock(CerebrusProvider::class)->makePartial();
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn($mockResponse);

        $result = $provider->breakdownTask(
            'Test Task',
            'Test description',
            []
        );

        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful()); // Should fallback gracefully
        $this->assertCount(4, $result->getTasks()); // Fallback tasks from parseTaskResponse
        $this->assertContains('AI response was not in expected JSON format', $result->getNotes());
    }

    public function test_task_breakdown_system_prompt_configuration()
    {
        config([
            'ai.prompts.task_breakdown.system' => 'Custom system prompt for testing'
        ]);

        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('buildTaskBreakdownSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($provider);

        $this->assertEquals('Custom system prompt for testing', $prompt);
    }

    public function test_task_breakdown_user_prompt_configuration()
    {
        config([
            'ai.prompts.task_breakdown.user' => 'Custom user prompt: {task_title}'
        ]);

        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('buildTaskBreakdownUserPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($provider, 'Test Task', 'Test description', []);

        $this->assertStringContainsString('Custom user prompt:', $prompt);
        $this->assertStringContainsString('Test Task', $prompt);
        $this->assertStringContainsString('Test description', $prompt);
    }

    public function test_task_breakdown_json_schema_validation()
    {
        $provider = new CerebrusProvider(config('ai.providers.cerebrus'));

        // Mock valid JSON response
        $mockResponse = Mockery::mock(AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn(json_encode([
            'tasks' => [
                [
                    'title' => 'Valid Subtask',
                    'description' => 'A valid subtask description',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'due_date' => '2025-12-31',
                ],
                [
                    'title' => 'Another Subtask',
                    'description' => 'Another valid subtask',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => '2026-01-15',
                ],
            ],
            'notes' => ['Breakdown analysis note'],
        ]));

        $provider = Mockery::mock(CerebrusProvider::class)->makePartial();
        $provider->shouldReceive('chat')->andReturn($mockResponse);

        $result = $provider->breakdownTask('Test', 'Description', []);

        $this->assertTrue($result->isSuccessful());
        $tasks = $result->getTasks();

        // Verify task structure
        foreach ($tasks as $task) {
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('priority', $task);
            $this->assertArrayHasKey('status', $task);
            $this->assertArrayHasKey('due_date', $task);
        }
    }
}
