<?php

namespace Tests\Feature;

use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Providers\CerebrasProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * This test specifically prevents the regression where AI providers
 * return 'subtasks' in JSON but the parsing logic looks for 'tasks'.
 */
class AISubtaskRegressionPreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.cerebras.driver' => 'cerebras',
            'ai.providers.cerebras.api_key' => 'test-key',
            'ai.providers.cerebras.base_url' => 'https://api.cerebras.ai/v1',
            'ai.providers.cerebras.model' => 'test-model',
            'ai.providers.cerebras.timeout' => 30,
            'ai.providers.cerebras.max_tokens' => 4000,
            'ai.providers.cerebras.temperature' => 0.7,
        ]);
    }

    /**
     * This test specifically verifies that the CerebrasProvider can parse
     * responses with 'subtasks' field (the current format from AIPromptService).
     *
     * This prevents regression where the provider was looking for 'tasks'
     * but the AI was returning 'subtasks'.
     */
    public function test_cerebras_provider_handles_subtasks_field_from_ai_prompt_service()
    {
        $provider = new CerebrasProvider(config('ai.providers.cerebras'));

        // This is the exact format that AIPromptService requests from the AI
        $mockResponse = Mockery::mock(\App\Services\AI\Contracts\AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn(json_encode([
            'subtasks' => [
                [
                    'title' => 'Design User Interface',
                    'description' => 'Create wireframes and mockups for the user interface',
                    'status' => 'pending',
                    'initial_story_points' => 5,
                    'current_story_points' => 5,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Implement Frontend Components',
                    'description' => 'Build React components based on the designs',
                    'status' => 'pending',
                    'initial_story_points' => 8,
                    'current_story_points' => 8,
                    'story_points_change_count' => 0,
                ],
                [
                    'title' => 'Add Responsive Styling',
                    'description' => 'Ensure the interface works on all device sizes',
                    'status' => 'pending',
                    'initial_story_points' => 3,
                    'current_story_points' => 3,
                    'story_points_change_count' => 0,
                ],
            ],
            'summary' => 'The task was broken down into 3 subtasks covering design, implementation, and responsive styling.',
            'notes' => ['Consider accessibility requirements', 'Test on multiple browsers'],
            'problems' => ['Limited design resources available'],
            'suggestions' => ['Use a design system for consistency'],
        ]));
        $mockResponse->shouldReceive('getModel')->andReturn('test-model');

        // Use reflection to access the parseTaskResponse method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('parseTaskResponse');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $mockResponse, 'Test Project', []);

        // Verify the response is parsed correctly
        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertCount(3, $result->getTasks());

        // Verify all subtasks are parsed correctly
        $tasks = $result->getTasks();
        $this->assertEquals('Design User Interface', $tasks[0]['title']);
        $this->assertEquals(5, $tasks[0]['initial_story_points']);
        $this->assertEquals('Implement Frontend Components', $tasks[1]['title']);
        $this->assertEquals(8, $tasks[1]['initial_story_points']);
        $this->assertEquals('Add Responsive Styling', $tasks[2]['title']);
        $this->assertEquals(3, $tasks[2]['initial_story_points']);

        // Verify communication fields are parsed
        $this->assertStringContainsString('broken down into 3 subtasks', $result->getSummary());
        $this->assertContains('Consider accessibility requirements', $result->getNotes());
        $this->assertContains('Limited design resources available', $result->getProblems());
        $this->assertContains('Use a design system for consistency', $result->getSuggestions());
    }

    /**
     * This test verifies that the provider still works with legacy 'tasks' format
     * for backward compatibility.
     */
    public function test_cerebras_provider_handles_legacy_tasks_field()
    {
        $provider = new CerebrasProvider(config('ai.providers.cerebras'));

        // Legacy format with 'tasks' field
        $mockResponse = Mockery::mock(\App\Services\AI\Contracts\AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn(json_encode([
            'tasks' => [
                [
                    'title' => 'Legacy Task 1',
                    'description' => 'Legacy description',
                    'status' => 'pending',
                    'initial_story_points' => 2,
                    'current_story_points' => 2,
                    'story_points_change_count' => 0,
                ],
            ],
            'summary' => 'Legacy format test',
        ]));
        $mockResponse->shouldReceive('getModel')->andReturn('test-model');

        // Use reflection to access the parseTaskResponse method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('parseTaskResponse');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $mockResponse, 'Test Project', []);

        // Verify the response is parsed correctly
        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertCount(1, $result->getTasks());
        $this->assertEquals('Legacy Task 1', $result->getTasks()[0]['title']);
        $this->assertEquals('Legacy format test', $result->getSummary());
    }

    /**
     * This test verifies that when both 'subtasks' and 'tasks' are present,
     * 'subtasks' takes priority (current format over legacy).
     */
    public function test_cerebras_provider_prioritizes_subtasks_over_tasks()
    {
        $provider = new CerebrasProvider(config('ai.providers.cerebras'));

        // Response with both fields - should prioritize 'subtasks'
        $mockResponse = Mockery::mock(\App\Services\AI\Contracts\AIResponse::class);
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getContent')->andReturn(json_encode([
            'subtasks' => [
                [
                    'title' => 'Current Format Task',
                    'description' => 'This should be used',
                    'status' => 'pending',
                    'initial_story_points' => 3,
                    'current_story_points' => 3,
                    'story_points_change_count' => 0,
                ],
            ],
            'tasks' => [
                [
                    'title' => 'Legacy Format Task',
                    'description' => 'This should be ignored',
                    'status' => 'pending',
                ],
            ],
            'summary' => 'Priority test',
        ]));
        $mockResponse->shouldReceive('getModel')->andReturn('test-model');

        // Use reflection to access the parseTaskResponse method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('parseTaskResponse');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $mockResponse, 'Test Project', []);

        // Verify 'subtasks' is used, not 'tasks'
        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertCount(1, $result->getTasks());
        $this->assertEquals('Current Format Task', $result->getTasks()[0]['title']);
        $this->assertEquals('This should be used', $result->getTasks()[0]['description']);
    }

    /**
     * This test verifies that the AI prompt service requests the correct format.
     * This ensures the AI will return 'subtasks' which our parsing logic expects.
     */
    public function test_ai_prompt_service_requests_subtasks_format()
    {
        $promptService = new \App\Services\AI\AIPromptService();

        $systemPrompt = $promptService->buildTaskBreakdownSystemPrompt();

        // Verify the prompt explicitly requests 'subtasks' format
        $this->assertStringContainsString('"subtasks": [', $systemPrompt);
        $this->assertStringContainsString('"title": "Subtask Title"', $systemPrompt);
        $this->assertStringContainsString('"description": "Detailed subtask description"', $systemPrompt);
        $this->assertStringContainsString('"initial_story_points": number', $systemPrompt);
        $this->assertStringContainsString('"current_story_points": number', $systemPrompt);
        $this->assertStringContainsString('"story_points_change_count": 0', $systemPrompt);

        // Verify it does NOT request the old 'tasks' format
        $this->assertStringNotContainsString('"tasks": [', $systemPrompt);
    }

    /**
     * This test verifies that the actual AI breakdown method works end-to-end
     * with the current format.
     */
    public function test_breakdown_task_method_works_with_subtasks_format()
    {
        $provider = new CerebrasProvider(config('ai.providers.cerebras'));

        // Test the public breakdownTask method
        $result = $provider->breakdownTask('Test Task', 'Test Description', []);

        // Verify it returns a valid response
        $this->assertInstanceOf(AITaskResponse::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertIsArray($result->getTasks());

        // If tasks are returned, verify they have the expected structure
        if (!empty($result->getTasks())) {
            $task = $result->getTasks()[0];
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('status', $task);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
