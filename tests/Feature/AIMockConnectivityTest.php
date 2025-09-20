<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIMockConnectivityTest extends TestCase
{
    public function test_ai_base_url_mock_responds_with_200(): void
    {
        $baseUrl = config('ai.providers.cerebrus.base_url');

        // Mock a successful response
        Http::fake([
            $baseUrl => Http::response('OK', 200),
        ]);

        $response = Http::timeout(10)->get($baseUrl);

        // Assert that we get a 200 response
        $this->assertEquals(200, $response->status());
        $this->assertEquals('OK', $response->body());
    }

    public function test_ai_chat_endpoint_mock_responds_with_200(): void
    {
        $baseUrl = config('ai.providers.cerebrus.base_url');

        // Mock a successful chat response
        Http::fake([
            $baseUrl.'/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'tasks' => [
                                    [
                                        'title' => 'Setup Project',
                                        'description' => 'Initialize the todo app project',
                                        'priority' => 'high',
                                        'status' => 'pending',
                                        'subtasks' => [],
                                    ],
                                ],
                                'summary' => 'Simple todo app project analysis',
                                'notes' => ['Project scope is well-defined'],
                                'problems' => [],
                                'suggestions' => ['Consider adding user authentication'],
                            ]),
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 150,
                ],
                'model' => 'llama3.1-8b',
            ], 200),
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer test-key',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($baseUrl.'/chat/completions', [
            'model' => 'llama3.1-8b',
            'messages' => [
                ['role' => 'user', 'content' => 'Generate tasks for a todo app'],
            ],
            'max_tokens' => 1000,
        ]);

        // Assert successful response
        $this->assertEquals(200, $response->status());

        $data = $response->json();
        $this->assertArrayHasKey('choices', $data);
        $this->assertNotEmpty($data['choices']);

        $content = $data['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);

        $this->assertArrayHasKey('tasks', $parsed);
        $this->assertArrayHasKey('summary', $parsed);
        $this->assertArrayHasKey('notes', $parsed);
        $this->assertArrayHasKey('suggestions', $parsed);
        $this->assertEquals('Setup Project', $parsed['tasks'][0]['title']);
    }

    public function test_ai_provider_handles_mock_responses_correctly(): void
    {
        $provider = new \App\Services\AI\Providers\CerebrusProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://api.test-cerebrus.ai/v1',
            'model' => 'llama3.1-8b',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'timeout' => 30,
        ]);

        // Mock the HTTP response
        Http::fake([
            'api.test-cerebrus.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'tasks' => [
                                    [
                                        'title' => 'Project Setup',
                                        'description' => 'Initialize project structure',
                                        'priority' => 'high',
                                        'status' => 'pending',
                                        'subtasks' => [],
                                    ],
                                ],
                                'summary' => 'Well-defined project scope',
                                'notes' => ['Good project description'],
                                'problems' => [],
                                'suggestions' => ['Consider adding tests'],
                            ]),
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 100,
                ],
                'model' => 'llama3.1-8b',
            ], 200),
        ]);

        $schema = [
            'tasks' => [],
            'summary' => '',
            'notes' => [],
            'problems' => [],
            'suggestions' => [],
        ];

        $response = $provider->generateTasks('Build a test app', $schema);

        $this->assertTrue($response->isSuccessful());
        $this->assertCount(1, $response->getTasks());
        $this->assertEquals('Project Setup', $response->getTasks()[0]['title']);
        $this->assertEquals('Well-defined project scope', $response->getSummary());
        $this->assertCount(2, $response->getNotes()); // Now includes service info + original note
        $this->assertCount(1, $response->getSuggestions());

        // Verify service info is added as first note
        $notes = $response->getNotes();
        $this->assertStringContainsString('Generated by: cerebrus', $notes[0]);
        $this->assertEquals('Good project description', $notes[1]);
    }
}
