<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\AIResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CerebrusProvider implements AIProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Generate a chat completion.
     */
    public function chat(array $messages, array $options = []): AIResponse
    {
        $startTime = microtime(true);

        try {
            $response = $this->makeRequest('/chat/completions', [
                'model' => $options['model'] ?? $this->config['model'],
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
                'temperature' => $options['temperature'] ?? $this->config['temperature'],
            ]);

            $responseTime = microtime(true) - $startTime;

            $content = $response['choices'][0]['message']['content'] ?? '';
            $tokensUsed = $response['usage']['total_tokens'] ?? null;

            $this->logRequest('chat', $messages, $content, $responseTime, $tokensUsed);

            return new AIResponse(
                content: $content,
                metadata: $response,
                model: $response['model'] ?? null,
                tokensUsed: $tokensUsed,
                responseTime: $responseTime
            );
        } catch (\Exception $e) {
            $this->logError('chat', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate tasks based on a project description.
     */
    public function generateTasks(string $projectDescription, array $options = []): array
    {
        $prompts = config('ai.prompts.task_generation');

        $messages = [
            ['role' => 'system', 'content' => $prompts['system']],
            ['role' => 'user', 'content' => str_replace('{description}', $projectDescription, $prompts['user'])],
        ];

        $response = $this->chat($messages, $options);

        try {
            return $response->parseJson();
        } catch (\InvalidArgumentException $e) {
            // If JSON parsing fails, return a fallback structure
            return $this->createFallbackTasks($projectDescription);
        }
    }

    /**
     * Analyze a project and provide insights.
     */
    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
    {
        $prompts = config('ai.prompts.project_analysis');

        $messages = [
            ['role' => 'system', 'content' => $prompts['system']],
            ['role' => 'user', 'content' => str_replace('{description}', $projectDescription, $prompts['user'])],
        ];

        $response = $this->chat($messages, $options);

        return $response->getContent();
    }

    /**
     * Suggest task improvements or next steps.
     */
    public function suggestTaskImprovements(array $tasks, array $options = []): array
    {
        $prompts = config('ai.prompts.task_suggestions');
        $tasksJson = json_encode($tasks);

        $messages = [
            ['role' => 'system', 'content' => $prompts['system']],
            ['role' => 'user', 'content' => str_replace('{tasks}', $tasksJson, $prompts['user'])],
        ];

        $response = $this->chat($messages, $options);

        try {
            return $response->parseJson();
        } catch (\InvalidArgumentException $e) {
            // Return text-based suggestions if JSON parsing fails
            return [
                'suggestions' => $response->getContent(),
                'type' => 'text'
            ];
        }
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return 'cerebrus';
    }

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']);
    }

    /**
     * Get provider configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Make an HTTP request to the Cerebrus API.
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        $client = $this->buildHttpClient();

        $response = $client->post($endpoint, $data);

        if ($response->failed()) {
            throw new \Exception("Cerebrus API request failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Build the HTTP client with proper headers and configuration.
     */
    protected function buildHttpClient(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout']);
    }

    /**
     * Log AI request for monitoring and debugging.
     */
    protected function logRequest(string $operation, array $input, string $output, float $responseTime, ?int $tokensUsed): void
    {
        if (!config('ai.logging.enabled')) {
            return;
        }

        Log::channel(config('ai.logging.channel', 'daily'))->info('AI Request', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'model' => $this->config['model'],
            'response_time' => $responseTime,
            'tokens_used' => $tokensUsed,
            'input' => config('ai.logging.log_requests') ? $input : '[REDACTED]',
            'output' => config('ai.logging.log_responses') ? $output : '[REDACTED]',
        ]);
    }

    /**
     * Log AI errors.
     */
    protected function logError(string $operation, string $error): void
    {
        if (!config('ai.logging.log_errors')) {
            return;
        }

        Log::channel(config('ai.logging.channel', 'daily'))->error('AI Request Failed', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'error' => $error,
        ]);
    }

    /**
     * Create fallback tasks when AI parsing fails.
     */
    public function createFallbackTasks(string $projectDescription): array
    {
        return [
            [
                'title' => 'Project Planning & Setup',
                'description' => 'Set up project structure and define requirements based on: ' . $projectDescription,
                'priority' => 'high',
                'status' => 'pending',
                'subtasks' => []
            ],
            [
                'title' => 'Core Development',
                'description' => 'Implement main functionality and features',
                'priority' => 'high',
                'status' => 'pending',
                'subtasks' => []
            ],
            [
                'title' => 'Testing & Quality Assurance',
                'description' => 'Write tests and ensure code quality',
                'priority' => 'medium',
                'status' => 'pending',
                'subtasks' => []
            ],
            [
                'title' => 'Documentation & Deployment',
                'description' => 'Create documentation and deploy the project',
                'priority' => 'low',
                'status' => 'pending',
                'subtasks' => []
            ],
        ];
    }
}
