<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\{AIProviderInterface, AIResponse, AITaskResponse};
use App\Services\AI\AIUsageService;
use App\Services\AI\AIPromptService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected array $defaultOptions;
    protected array $config;
    protected ?string $organization;
    protected ?AIPromptService $promptService = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? throw new \InvalidArgumentException('OpenAI API key is required');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->organization = $config['organization'] ?? null;
        $this->promptService = new AIPromptService();

        $this->defaultOptions = [
            'model' => $config['model'] ?? 'gpt-4',
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 4000,
            'timeout' => $config['timeout'] ?? 30,
        ];
    }

    public function chat(array $messages, array $options = []): AIResponse
    {
        try {
            $payload = array_merge($this->defaultOptions, $options, [
                'messages' => $messages,
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->timeout($payload['timeout'])
                ->post($this->baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? 'OpenAI API request failed';
                $this->logError('chat', $error, $response->status());
                throw new \Exception($error);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            $this->logRequest('chat', $messages, $content,
                $data['usage']['total_tokens'] ?? 0,
                $data['model'] ?? null
            );

            return new AIResponse(
                content: $content,
                metadata: $data,
                model: $data['model'] ?? null,
                tokensUsed: $data['usage']['total_tokens'] ?? 0,
                responseTime: 0
            );

        } catch (\Exception $e) {
            $this->logError('chat', $e->getMessage());
            throw $e;
        }
    }

    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse
    {
        try {
            if (!$this->promptService) {
                $this->promptService = new AIPromptService();
            }

            $systemPrompt = $this->promptService->buildTaskGenerationSystemPrompt();
            $userPrompt = $this->promptService->buildTaskGenerationUserPrompt($projectDescription, $options);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat($messages, array_merge([
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ], $options));

            if (!$response->isSuccessful()) {
                $this->logError('task_generation', $response->getErrorMessage() ?? 'Unknown error');
                return $this->createFallbackTaskGeneration($projectDescription);
            }

            $content = $this->cleanResponseContent($response->getContent());
            $this->logRequest('task_generation', [$projectDescription], $content, 0, null);

            return $this->parseTaskGenerationResponse($content, $projectDescription);

        } catch (\Exception $e) {
            $this->logError('task_generation', $e->getMessage());
            return $this->createFallbackTaskGeneration($projectDescription);
        }
    }

    public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): AITaskResponse
    {
        try {
            if (!$this->promptService) {
                $this->promptService = new AIPromptService();
            }

            $systemPrompt = $this->promptService->buildTaskBreakdownSystemPrompt();
            $userPrompt = $this->promptService->buildTaskBreakdownUserPrompt($taskTitle, $taskDescription, $context);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->chat($messages, array_merge([
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ], $options));

            if (!$response->isSuccessful()) {
                $this->logError('task_breakdown', $response->getErrorMessage() ?? 'Unknown error');
                return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, $context['project']['due_date'] ?? null);
            }

            $content = $this->cleanResponseContent($response->getContent());
            $this->logRequest('task_breakdown', [$taskTitle, $taskDescription], $content, 0, null);

            return $this->parseTaskBreakdownResponse($content, $taskTitle, $taskDescription);

        } catch (\Exception $e) {
            $this->logError('task_breakdown', $e->getMessage());
            return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, $context['project']['due_date'] ?? null);
        }
    }

    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
    {
        $systemPrompt = 'You are a senior project consultant who analyzes project requirements and provides strategic insights.';

        $userPrompt = str_replace('{description}', $projectDescription,
            'Analyze this project: "{description}". Provide insights about scope, complexity, timeline estimates, potential risks, and recommended technologies.'
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $response = $this->chat($messages, $options);
        return $response->isSuccessful() ? $response->getContent() : 'Analysis unavailable due to API error.';
    }

    public function suggestTaskImprovements(array $tasks, array $options = []): array
    {
        $systemPrompt = 'You are an AI assistant that helps improve task management by suggesting optimizations and next steps.';

        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $userPrompt = str_replace('{tasks}', $tasksJson,
            'Given these existing tasks: {tasks}, suggest improvements, identify missing tasks, or recommend task prioritization changes.'
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $response = $this->chat($messages, $options);

        if ($response->isSuccessful()) {
            // Parse the response into an array of suggestions
            $content = $response->getContent();
            return array_filter(array_map('trim', explode("\n", $content)));
        }

        return ['Unable to generate suggestions due to API error.'];
    }

    public function testConnection(): array
    {
        try {
            $messages = [['role' => 'user', 'content' => 'Hello, this is a connection test.']];
            $response = $this->chat($messages, ['max_tokens' => 50]);

            if ($response->isSuccessful()) {
                return [
                    'success' => true,
                    'message' => 'OpenAI connection successful',
                    'response' => $response->getContent(),
                ];
            }

            return [
                'success' => false,
                'message' => 'OpenAI connection failed',
                'error' => $response->getErrorMessage(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'OpenAI connection test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    // Protected helper methods

    protected function cleanResponseContent(string $content): string
    {
        // Remove markdown code blocks
        $content = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $content);

        // Remove leading/trailing whitespace
        return trim($content);
    }

    protected function parseTaskGenerationResponse(string $content, string $projectDescription): AITaskResponse
    {
        try {
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from OpenAI');
            }

            $tasks = $data['tasks'] ?? [];
            $projectTitle = $data['project_title'] ?? 'Untitled Project';

            // Add service information to notes
            $notes = $data['notes'] ?? [];
            $notes[] = "Generated using OpenAI ({$this->defaultOptions['model']})";

            return AITaskResponse::success(
                tasks: $tasks,
                projectTitle: $projectTitle,
                notes: $notes,
                summary: $data['summary'] ?? '',
                problems: $data['problems'] ?? [],
                suggestions: $data['suggestions'] ?? []
            );

        } catch (\Exception $e) {
            $this->logError('parse_task_generation', $e->getMessage());
            return $this->createFallbackTaskGeneration($projectDescription);
        }
    }

    protected function parseTaskBreakdownResponse(string $content, string $taskTitle, string $taskDescription): AITaskResponse
    {
        try {
            // Try to parse as JSON first
            $data = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['subtasks'])) {
                $tasks = $data['subtasks'];
            } else {
                // Parse as plain text list
                $tasks = $this->parseTextListToTasks($content);
            }

            // Add service information to notes
            $notes = ["Generated using OpenAI ({$this->defaultOptions['model']})"];

            return AITaskResponse::success(
                tasks: $tasks,
                projectTitle: null,
                notes: $notes,
                summary: "Task breakdown for: {$taskTitle}",
                problems: [],
                suggestions: []
            );

        } catch (\Exception $e) {
            $this->logError('parse_task_breakdown', $e->getMessage());
            return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, null);
        }
    }

    protected function parseTextListToTasks(string $content): array
    {
        $tasks = [];
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        foreach ($lines as $line) {
            // Enhanced regex patterns to match more formats
            if (preg_match('/^\d+\.\s*(.+)/', $line, $matches) ||        // "1. Task title"
                preg_match('/^[-*]\s*(.+)/', $line, $matches) ||          // "- Task title" or "* Task title"
                preg_match('/^\d+\)\s*(.+)/', $line, $matches) ||         // "1) Task title"
                preg_match('/^Task\s*\d+:\s*(.+)/i', $line, $matches) ||  // "Task 1: Title"
                preg_match('/^[•▪▫]\s*(.+)/', $line, $matches) ||         // Bullet points
                preg_match('/^\s*[▶►]\s*(.+)/', $line, $matches)) {       // Arrow bullets

                $title = trim($matches[1]);
                if (!empty($title) && strlen($title) > 3) { // Ensure meaningful titles
                    $tasks[] = [
                        'title' => $title,
                        'description' => '',
                        'status' => 'pending',
                        'size' => $this->generateFallbackSize($title),
                    ];
                }
            } else if (!empty($line) &&
                      !preg_match('/^(Here|The|These|Below|Following|I|You|Please|Let|This)/i', $line) &&
                      strlen(trim($line)) > 10 &&
                      !str_contains($line, '```') &&
                      count($tasks) < 10) {
                // If line doesn't match patterns but looks like a task, include it
                $cleanTitle = trim($line);
                if (!empty($cleanTitle)) {
                    $tasks[] = [
                        'title' => $cleanTitle,
                        'description' => '',
                        'status' => 'pending',
                        'size' => $this->generateFallbackSize($cleanTitle),
                    ];
                }
            }
        }

        // If no tasks found, try a more aggressive approach
        if (empty($tasks)) {
            $allLines = array_filter(array_map('trim', explode("\n", $content)));
            foreach ($allLines as $line) {
                if (strlen(trim($line)) > 5 &&
                    !str_contains($line, '```') &&
                    !preg_match('/^(Here|The|These|Below|Following|I|You|Please|Let|This|Based|In)/i', $line)) {
                    $tasks[] = [
                        'title' => trim($line),
                        'description' => '',
                        'status' => 'pending',
                        'size' => $this->generateFallbackSize(trim($line)),
                    ];

                    if (count($tasks) >= 5) break; // Limit to reasonable number
                }
            }
        }

        return $tasks;
    }

    protected function createFallbackTaskGeneration(string $projectDescription): AITaskResponse
    {
        $tasks = [
            [
                'title' => 'Project Planning',
                'description' => 'Define project requirements, scope, and timeline',
                'status' => 'pending',
            ],
            [
                'title' => 'Initial Setup',
                'description' => 'Set up development environment and basic project structure',
                'status' => 'pending',
            ],
        ];

        return AITaskResponse::success(
            tasks: $tasks,
            projectTitle: 'Fallback Project',
            notes: ['OpenAI service temporarily unavailable'],
            summary: 'Fallback task generation due to API error',
            problems: ['API connection failed'],
            suggestions: ['Try again later or check API configuration']
        );
    }

    /**
     * Generate fallback size based on task content.
     */
    protected function generateFallbackSize(string $title): string
    {
        $text = strtolower($title);

        // Heuristic sizing based on keywords
        $complexityKeywords = [
            'xs' => ['fix', 'bug', 'typo', 'small', 'quick', 'minor', 'update', 'change'],
            's' => ['add', 'create', 'implement', 'simple', 'basic', 'standard'],
            'm' => ['feature', 'component', 'module', 'integration', 'api', 'database'],
            'l' => ['system', 'architecture', 'refactor', 'migration', 'complex', 'major'],
            'xl' => ['rewrite', 'redesign', 'overhaul', 'platform', 'framework', 'enterprise']
        ];

        foreach ($complexityKeywords as $size => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $size;
                }
            }
        }

        // Default to medium if no keywords match
        return 'm';
    }

    protected function createFallbackTaskBreakdown(string $taskTitle, string $taskDescription, ?string $projectDueDate = null): AITaskResponse
    {
        $tasks = [
            [
                'title' => 'Research and Planning',
                'description' => "Research requirements and plan approach for: {$taskTitle}",
                'status' => 'pending',
                'size' => 'm',
            ],
            [
                'title' => 'Implementation',
                'description' => "Implement the main functionality for: {$taskTitle}",
                'status' => 'pending',
                'size' => 'l',
            ],
        ];

        return AITaskResponse::success(
            tasks: $tasks,
            projectTitle: null,
            notes: ['OpenAI service temporarily unavailable'],
            summary: "Fallback breakdown for: {$taskTitle}",
            problems: ['API connection failed'],
            suggestions: ['Try again later or check API configuration']
        );
    }

    protected function logRequest(string $operation, array $input, string $output, int $tokens = 0, ?string $model = null): void
    {
        // Calculate estimated cost (rough calculation for gpt-3.5-turbo)
        $estimatedCost = $tokens * 0.0000015; // $0.0015 per 1K tokens

        // Log to AI Usage Service
        try {
            $aiUsageService = new AIUsageService();
            $aiUsageService->logUsage('openai', $model ?? $this->defaultOptions['model'], $tokens, $estimatedCost);
        } catch (\Exception $e) {
            // Don't fail if usage logging fails
            Log::warning('Failed to log AI usage', ['error' => $e->getMessage()]);
        }

        if (config('ai.logging.enabled') && config('ai.logging.log_requests')) {
            Log::channel(config('ai.logging.channel', 'daily'))->info('OpenAI API Request', [
                'provider' => 'openai',
                'operation' => $operation,
                'model' => $model ?? $this->defaultOptions['model'],
                'tokens' => $tokens,
                'cost_estimated' => $estimatedCost,
                'input_length' => strlen(json_encode($input)),
                'output_length' => strlen($output),
            ]);
        }
    }

    protected function logError(string $operation, string $error, ?int $statusCode = null): void
    {
        // Log error to AI Usage Service
        try {
            $aiUsageService = new AIUsageService();
            $aiUsageService->logError('openai', $this->defaultOptions['model'], $error);
        } catch (\Exception $e) {
            // Don't fail if usage logging fails
            Log::warning('Failed to log AI error', ['error' => $e->getMessage()]);
        }

        if (config('ai.logging.enabled') && config('ai.logging.log_errors')) {
            Log::channel(config('ai.logging.channel', 'daily'))->error('OpenAI API Error', [
                'provider' => 'openai',
                'operation' => $operation,
                'error' => $error,
                'status_code' => $statusCode,
            ]);
        }
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get maximum story points for a given T-shirt size.
     * Subtasks must be smaller than their parent's maximum.
     */
}
