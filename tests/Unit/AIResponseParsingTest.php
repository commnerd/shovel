<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Providers\CerebrusProvider;
use Tests\TestCase;

class AIResponseParsingTest extends TestCase
{
    private CerebrusProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new CerebrusProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.cerebras.ai/v1',
            'model' => 'llama-4-scout-17b-16e-instruct',
            'max_tokens' => 2000,
            'temperature' => 0.3,
            'timeout' => 30,
        ]);
    }

    public function test_clean_response_content_removes_markdown(): void
    {
        $content = '```json
{
  "tasks": [
    {
      "title": "Test Task",
      "description": "Test Description",
      "priority": "high",
      "status": "pending",
      "subtasks": []
    }
  ],
  "summary": "Test summary"
}
```';

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('cleanResponseContent');
        $method->setAccessible(true);

        $cleaned = $method->invoke($this->provider, $content);

        $this->assertStringStartsWith('{', $cleaned);
        $this->assertStringEndsWith('}', $cleaned);
        $this->assertStringNotContainsString('```', $cleaned);

        // Should be valid JSON
        $decoded = json_decode($cleaned, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('tasks', $decoded);
    }

    public function test_clean_response_content_extracts_json_from_text(): void
    {
        $content = 'Here is the task breakdown you requested:

{
  "tasks": [
    {
      "title": "Setup Project",
      "description": "Initialize the project",
      "priority": "high",
      "status": "pending",
      "subtasks": []
    }
  ],
  "summary": "Simple project setup"
}

I hope this helps with your project planning!';

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('cleanResponseContent');
        $method->setAccessible(true);

        $cleaned = $method->invoke($this->provider, $content);

        $this->assertStringStartsWith('{', $cleaned);
        $this->assertStringEndsWith('}', $cleaned);
        $this->assertStringNotContainsString('Here is the task breakdown', $cleaned);
        $this->assertStringNotContainsString('I hope this helps', $cleaned);

        // Should be valid JSON
        $decoded = json_decode($cleaned, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('Setup Project', $decoded['tasks'][0]['title']);
    }

    public function test_parse_task_response_handles_cleaned_json(): void
    {
        $jsonContent = json_encode([
            'tasks' => [
                [
                    'title' => 'Test Task',
                    'description' => 'Test Description',
                    'priority' => 'high',
                    'status' => 'pending',
                    'subtasks' => [],
                ],
            ],
            'summary' => 'Test project analysis',
            'notes' => ['Good project scope'],
            'problems' => [],
            'suggestions' => ['Consider adding tests'],
        ]);

        $aiResponse = new AIResponse($jsonContent);

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('parseTaskResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->provider, $aiResponse, 'Test project');

        $this->assertTrue($result->isSuccessful());
        $this->assertCount(1, $result->getTasks());
        $this->assertEquals('Test Task', $result->getTasks()[0]['title']);
        $this->assertEquals('Test project analysis', $result->getSummary());
        $this->assertCount(1, $result->getNotes());
        $this->assertCount(1, $result->getSuggestions());
    }

    public function test_parse_task_response_handles_malformed_json(): void
    {
        $malformedContent = 'This is not JSON at all, just plain text response about tasks.';

        $aiResponse = new AIResponse($malformedContent);

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('parseTaskResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->provider, $aiResponse, 'Test project');

        $this->assertTrue($result->isSuccessful()); // Should succeed with fallback
        $this->assertCount(4, $result->getTasks()); // Fallback tasks
        $this->assertContains('AI response was not in expected JSON format', $result->getNotes());
        $this->assertNotEmpty($result->getProblems());
        $this->assertStringContainsString('This is not JSON', $result->getProblems()[1]);
    }

    public function test_enhanced_prompts_include_json_requirements(): void
    {
        $prompts = config('ai.prompts.task_generation');

        $this->assertStringContainsString('valid JSON only', $prompts['system']);
        $this->assertStringContainsString('CRITICAL: You must respond with ONLY a valid JSON object', $prompts['user']);
        $this->assertStringContainsString('"tasks":', $prompts['user']);
        $this->assertStringContainsString('"summary":', $prompts['user']);
        $this->assertStringContainsString('"notes":', $prompts['user']);
        $this->assertStringContainsString('"problems":', $prompts['user']);
        $this->assertStringContainsString('"suggestions":', $prompts['user']);
    }
}
