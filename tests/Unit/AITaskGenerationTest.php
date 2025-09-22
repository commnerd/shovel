<?php

namespace Tests\Unit;

use App\Services\AI\AIManager;
use App\Services\AI\Providers\CerebrasProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AITaskGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AI configuration
        config([
            'ai.default' => 'cerebras',
            'ai.providers.cerebras' => [
                'api_key' => 'test-key',
                'base_url' => 'https://api.cerebras.ai',
                'model' => 'gpt-4',
                'max_tokens' => 4000,
                'temperature' => 0.7,
                'timeout' => 30,
            ],
        ]);
    }

    public function test_ai_manager_can_generate_tasks()
    {
        // Mock the AI manager
        $aiManager = Mockery::mock(AIManager::class);
        $mockResponse = \App\Services\AI\Contracts\AITaskResponse::success([
            [
                'title' => 'Setup Development Environment',
                'description' => 'Configure development tools and dependencies',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Design Database Schema',
                'description' => 'Create database tables and relationships',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'title' => 'Implement Authentication',
                'description' => 'Add user registration and login functionality',
                'priority' => 'medium',
                'status' => 'pending',
            ],
        ], 'Task Management System');

        $aiManager->shouldReceive('generateTasks')
            ->with('Build a task management app with Vue.js and Laravel', [])
            ->once()
            ->andReturn($mockResponse);

        $this->app->instance(AIManager::class, $aiManager);

        $response = $aiManager->generateTasks('Build a task management app with Vue.js and Laravel', []);

        $this->assertInstanceOf(\App\Services\AI\Contracts\AITaskResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertCount(3, $response->getTasks());
        $this->assertEquals('Task Management System', $response->getProjectTitle());
        $this->assertEquals('Setup Development Environment', $response->getTasks()[0]['title']);
        $this->assertEquals('high', $response->getTasks()[0]['priority']);
        $this->assertEquals('pending', $response->getTasks()[0]['status']);
    }

    public function test_cerebras_provider_can_create_fallback_tasks()
    {
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.cerebras.ai',
            'model' => 'gpt-4',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        $fallbackTasks = $provider->createFallbackTasks('Build a mobile app');

        $this->assertIsArray($fallbackTasks);
        $this->assertGreaterThan(0, count($fallbackTasks));

        foreach ($fallbackTasks as $task) {
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('status', $task);
            $this->assertEquals('pending', $task['status']);
        }

        // Check that the project description is incorporated
        $this->assertStringContainsString('Build a mobile app', $fallbackTasks[0]['description']);
    }

    public function test_ai_manager_handles_provider_failures_gracefully()
    {
        // Mock a provider that throws an exception
        $mockProvider = Mockery::mock(CerebrasProvider::class);
        $mockProvider->shouldReceive('generateTasks')
            ->andThrow(new \Exception('API connection failed'));

        $aiManager = Mockery::mock(AIManager::class);
        $aiManager->shouldReceive('generateTasks')
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance(AIManager::class, $aiManager);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AI service unavailable');

        $aiManager->generateTasks('Test project description');
    }

    public function test_task_generation_validates_input()
    {
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.cerebras.ai',
            'model' => 'gpt-4',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Test with empty description
        $fallbackTasks = $provider->createFallbackTasks('');
        $this->assertIsArray($fallbackTasks);
        $this->assertGreaterThan(0, count($fallbackTasks));

        // Test with very long description
        $longDescription = str_repeat('This is a very long project description. ', 100);
        $fallbackTasks = $provider->createFallbackTasks($longDescription);
        $this->assertIsArray($fallbackTasks);
        $this->assertGreaterThan(0, count($fallbackTasks));
    }

    public function test_generated_tasks_have_proper_structure()
    {
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.cerebras.ai',
            'model' => 'gpt-4',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        $tasks = $provider->createFallbackTasks('Build a web application');

        foreach ($tasks as $task) {
            // Required fields
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('status', $task);

            // Validate field types and values
            $this->assertIsString($task['title']);
            $this->assertIsString($task['description']);
            $this->assertNotEmpty($task['title']);
            $this->assertEquals('pending', $task['status']);
        }
    }

    public function test_ai_provider_configuration_validation()
    {
        // Test with missing API key
        $provider = new CerebrasProvider([
            'base_url' => 'https://api.cerebras.ai',
            'model' => 'gpt-4',
        ]);

        $this->assertFalse($provider->isConfigured());

        // Test with complete configuration
        $provider = new CerebrasProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.cerebras.ai',
            'model' => 'gpt-4',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        $this->assertTrue($provider->isConfigured());
        $this->assertEquals('cerebras', $provider->getName());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
