<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Providers\CerebrusProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CerebrusProviderTest extends TestCase
{
    private array $config;

    private CerebrusProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'api_key' => 'test-api-key',
            'base_url' => 'https://api.cerebras.ai/v1',
            'model' => 'llama3.1-8b',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'timeout' => 30,
        ];

        $this->provider = new CerebrusProvider($this->config);
    }

    public function test_provider_name_is_cerebrus(): void
    {
        $this->assertEquals('cerebrus', $this->provider->getName());
    }

    public function test_provider_is_configured_with_api_key(): void
    {
        $this->assertTrue($this->provider->isConfigured());
    }

    public function test_provider_is_not_configured_without_api_key(): void
    {
        $configWithoutKey = array_merge($this->config, ['api_key' => '']);
        $provider = new CerebrusProvider($configWithoutKey);

        $this->assertFalse($provider->isConfigured());
    }

    public function test_provider_returns_correct_config(): void
    {
        $config = $this->provider->getConfig();

        $this->assertEquals($this->config, $config);
    }

    public function test_chat_method_makes_correct_api_call(): void
    {
        Http::fake([
            'api.cerebras.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Test response',
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 50,
                ],
                'model' => 'llama3.1-8b',
            ]),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Test message'],
        ];

        $response = $this->provider->chat($messages);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Test response', $response->getContent());
        $this->assertEquals(50, $response->getTokensUsed());

        Http::assertSent(function (Request $request) use ($messages) {
            return $request->url() === 'https://api.cerebras.ai/v1/chat/completions' &&
                   $request['messages'] === $messages &&
                   $request['model'] === 'llama3.1-8b' &&
                   $request['max_tokens'] === 4096 &&
                   $request['temperature'] === 0.7;
        });
    }

    public function test_generate_tasks_with_valid_response(): void
    {
        $mockResponse = [
            'tasks' => [
                [
                    'title' => 'Setup Project',
                    'description' => 'Initialize project structure',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            'summary' => 'Project analysis complete',
            'notes' => ['Good project scope'],
            'problems' => [],
            'suggestions' => ['Consider adding tests'],
        ];

        Http::fake([
            'api.cerebras.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockResponse),
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 100,
                ],
                'model' => 'llama3.1-8b',
            ]),
        ]);

        $schema = ['tasks' => [], 'summary' => '', 'notes' => [], 'problems' => [], 'suggestions' => []];
        $response = $this->provider->generateTasks('Build a web app', $schema);

        $this->assertInstanceOf(AITaskResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertCount(1, $response->getTasks());
        $this->assertEquals('Setup Project', $response->getTasks()[0]['title']);
        $this->assertEquals('Project analysis complete', $response->getSummary());
        $this->assertCount(1, $response->getNotes());
        $this->assertCount(1, $response->getSuggestions());
    }

    public function test_generate_tasks_with_invalid_json_returns_fallback(): void
    {
        Http::fake([
            'api.cerebras.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Invalid JSON response',
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 50,
                ],
                'model' => 'llama3.1-8b',
            ]),
        ]);

        $response = $this->provider->generateTasks('Build a web app');

        $this->assertInstanceOf(AITaskResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertCount(4, $response->getTasks()); // Fallback tasks
        $this->assertContains('AI response was not in expected JSON format', $response->getNotes());
    }

    public function test_generate_tasks_handles_api_failure(): void
    {
        Http::fake([
            'api.cerebras.ai/*' => Http::response('API Error', 500),
        ]);

        $response = $this->provider->generateTasks('Build a web app');

        $this->assertInstanceOf(AITaskResponse::class, $response);
        $this->assertTrue($response->isSuccessful()); // Should succeed with fallback
        $this->assertCount(4, $response->getTasks()); // Fallback tasks
        $this->assertNotEmpty($response->getProblems());
    }

    public function test_validate_tasks_cleans_invalid_data(): void
    {
        $invalidTasks = [
            [
                'title' => 'Valid Task',
                'description' => 'Good description',
                'priority' => 'high',
                'status' => 'pending',
                'subtasks' => [],
            ],
            [
                // Missing title - should be filtered or fixed
                'description' => 'Missing title',
                'priority' => 'invalid_priority',
                'status' => 'invalid_status',
            ],
            [
                'title' => 'Partial Task',
                // Missing other fields - should get defaults
            ],
        ];

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('validateTasks');
        $method->setAccessible(true);

        $validatedTasks = $method->invoke($this->provider, $invalidTasks);

        $this->assertCount(2, $validatedTasks); // Only valid tasks
        $this->assertEquals('Valid Task', $validatedTasks[0]['title']);
        $this->assertEquals('Partial Task', $validatedTasks[1]['title']);
        $this->assertEquals('medium', $validatedTasks[1]['priority']); // Default
        $this->assertEquals('pending', $validatedTasks[1]['status']); // Default
    }

    public function test_create_fallback_tasks(): void
    {
        $fallbackTasks = $this->provider->createFallbackTasks('Test project description');

        $this->assertCount(4, $fallbackTasks);
        $this->assertEquals('Project Planning & Setup', $fallbackTasks[0]['title']);
        $this->assertStringContainsString('Test project description', $fallbackTasks[0]['description']);

        // Check all tasks have required fields
        foreach ($fallbackTasks as $task) {
            $this->assertArrayHasKey('title', $task);
            $this->assertArrayHasKey('description', $task);
            $this->assertArrayHasKey('priority', $task);
            $this->assertArrayHasKey('status', $task);
            $this->assertArrayHasKey('subtasks', $task);
        }
    }

    public function test_build_system_prompt_with_schema(): void
    {
        $basePrompt = 'You are an AI assistant.';
        $schema = ['tasks' => [], 'summary' => ''];

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('buildSystemPromptWithSchema');
        $method->setAccessible(true);

        $enhancedPrompt = $method->invoke($this->provider, $basePrompt, $schema);

        $this->assertStringContainsString($basePrompt, $enhancedPrompt);
        $this->assertStringContainsString('valid JSON object', $enhancedPrompt);
        $this->assertStringContainsString('tasks', $enhancedPrompt);
        $this->assertStringContainsString('summary', $enhancedPrompt);
        $this->assertStringContainsString('notes', $enhancedPrompt);
        $this->assertStringContainsString('problems', $enhancedPrompt);
        $this->assertStringContainsString('suggestions', $enhancedPrompt);
    }

    public function test_handles_string_to_array_conversion(): void
    {
        $mockResponse = [
            'tasks' => [
                [
                    'title' => 'Test Task',
                    'description' => 'Test Description',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            'summary' => 'Test summary',
            'notes' => 'Single note string', // String instead of array
            'problems' => 'Single problem string', // String instead of array
            'suggestions' => ['Proper array suggestion'], // Already array
        ];

        Http::fake([
            'api.cerebras.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockResponse),
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 100,
                ],
                'model' => 'llama3.1-8b',
            ]),
        ]);

        $response = $this->provider->generateTasks('Test project');

        $this->assertTrue($response->isSuccessful());
        $this->assertIsArray($response->getNotes());
        $this->assertIsArray($response->getProblems());
        $this->assertIsArray($response->getSuggestions());
        $this->assertEquals(['Single note string'], $response->getNotes());
        $this->assertEquals(['Single problem string'], $response->getProblems());
        $this->assertEquals(['Proper array suggestion'], $response->getSuggestions());
    }
}
