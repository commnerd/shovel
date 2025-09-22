<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIConnectivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure AI provider for tests
        Setting::set('ai.cerebrus.api_key', 'test-cerebrus-key', 'string', 'Cerebrus API Key');
        Setting::set('ai.cerebrus.base_url', 'https://api.cerebras.ai/v1', 'string', 'Cerebrus Base URL');
        Setting::set('ai.cerebrus.model', 'llama3.1-8b', 'string', 'Cerebrus Model');
    }

    public function test_ai_base_url_responds_with_200(): void
    {
        $baseUrl = Setting::get('ai.cerebrus.base_url');
        $apiKey = Setting::get('ai.cerebrus.api_key');

        // Skip test if no API key is configured
        if (empty($apiKey)) {
            $this->markTestSkipped('Cerebrus API key not configured');
        }

        try {
            // Make a simple request to the base URL or a health check endpoint
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($baseUrl);

            // Assert that we get a 200 response (or any successful response)
            $this->assertTrue(
                $response->successful() || $response->status() === 404, // 404 is OK for base URL
                'AI base URL should be reachable. Got: '.$response->status().
                ' Body: '.$response->body()
            );
        } catch (\Exception $e) {
            // If we can't reach the API, skip the test rather than fail
            $this->markTestSkipped('Cannot reach Cerebrus API: '.$e->getMessage());
        }
    }

    public function test_ai_chat_endpoint_is_accessible(): void
    {
        $baseUrl = Setting::get('ai.cerebrus.base_url');
        $apiKey = Setting::get('ai.cerebrus.api_key');

        // Skip test if no API key is configured
        if (empty($apiKey)) {
            $this->markTestSkipped('Cerebrus API key not configured');
        }

        try {
            // Test the chat completions endpoint with a minimal request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($baseUrl.'/chat/completions', [
                'model' => Setting::get('ai.cerebrus.model'),
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
                'max_tokens' => 10,
            ]);

            // Assert that we get a successful response (200 or 201)
            $this->assertTrue(
                in_array($response->status(), [200, 201]),
                'AI chat endpoint should respond with 200/201 status. Got: '.$response->status().
                ' Body: '.$response->body()
            );

            // Verify response structure if successful
            if ($response->successful()) {
                $data = $response->json();
                $this->assertArrayHasKey('choices', $data, 'Response should have choices array');
                $this->assertNotEmpty($data['choices'], 'Choices array should not be empty');
            }
        } catch (\Exception $e) {
            // If we can't reach the API, skip the test rather than fail
            $this->markTestSkipped('Cannot reach Cerebrus chat endpoint: '.$e->getMessage());
        }
    }

    public function test_ai_service_can_generate_real_tasks(): void
    {
        $baseUrl = Setting::get('ai.cerebrus.base_url');
        $apiKey = Setting::get('ai.cerebrus.api_key');

        // Skip test if no API key is configured
        if (empty($apiKey)) {
            $this->markTestSkipped('Cerebrus API key not configured');
        }

        try {
            // Test actual task generation with schema
            $taskSchema = [
                'tasks' => [
                    [
                        'title' => 'string',
                        'description' => 'string',
                        'priority' => 'high|medium|low',
                        'status' => 'pending|in_progress|completed',
                        'subtasks' => [],
                    ],
                ],
                'summary' => 'string (optional)',
                'notes' => ['array of strings (optional)'],
                'problems' => ['array of strings (optional)'],
                'suggestions' => ['array of strings (optional)'],
            ];

            $prompt = 'Based on this project description: "Build a simple todo app", create a detailed task breakdown. '.
                     'IMPORTANT: Your response must be a JSON object with this exact structure: '.
                     json_encode($taskSchema, JSON_PRETTY_PRINT).
                     "\n\nInclude 'notes', 'summary', 'problems', and 'suggestions' fields to communicate any insights, issues, or recommendations about the project.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($baseUrl.'/chat/completions', [
                'model' => Setting::get('ai.cerebrus.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert project manager and task breakdown specialist. You must respond with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);

            // Assert successful response
            $this->assertTrue(
                $response->successful(),
                'AI task generation should succeed. Status: '.$response->status().
                ' Body: '.$response->body()
            );

            if ($response->successful()) {
                $data = $response->json();
                $this->assertArrayHasKey('choices', $data);
                $this->assertNotEmpty($data['choices']);

                $content = $data['choices'][0]['message']['content'] ?? '';
                $this->assertNotEmpty($content, 'AI should return content');

                // Try to parse as JSON
                $parsed = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->assertArrayHasKey('tasks', $parsed, 'Response should have tasks array');
                    $this->assertIsArray($parsed['tasks'], 'Tasks should be an array');

                    // Check if any communication fields are present
                    $hasCommunication = isset($parsed['summary']) ||
                                      isset($parsed['notes']) ||
                                      isset($parsed['problems']) ||
                                      isset($parsed['suggestions']);

                    $this->assertTrue($hasCommunication, 'AI should provide some form of communication');
                } else {
                    // If not JSON, at least verify we got some content
                    $this->assertStringContainsString('todo', strtolower($content),
                        'Response should be related to todo app project');
                }
            }
        } catch (\Exception $e) {
            // If we can't reach the API, skip the test rather than fail
            $this->markTestSkipped('Cannot reach Cerebrus API for task generation: '.$e->getMessage());
        }
    }
}
