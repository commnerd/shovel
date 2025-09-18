<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Contracts\AITaskResponse;
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
     * Generate tasks based on a project description with schema validation.
     */
    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse
    {
        $prompts = config('ai.prompts.task_generation');

        // Build enhanced prompt with schema
        $systemPrompt = $prompts['system'] . "\n\nIMPORTANT: You are operating in JSON-only mode. Do not include any explanatory text, markdown formatting, or code blocks. Return only the raw JSON object.";
        $userPrompt = str_replace('{description}', $projectDescription, $prompts['user']);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            // Add JSON-specific options for better structured output
            $jsonOptions = array_merge($options, [
                'temperature' => 0.3, // Lower temperature for more consistent JSON
                'max_tokens' => min($options['max_tokens'] ?? 2000, 2000), // Reasonable limit for JSON
            ]);

            $response = $this->chat($messages, $jsonOptions);
            return $this->parseTaskResponse($response, $projectDescription);
        } catch (\Exception $e) {
            $this->logError('generateTasks', $e->getMessage());

            // Return fallback response
            return AITaskResponse::success(
                tasks: $this->createFallbackTasks($projectDescription),
                projectTitle: $this->generateFallbackTitle($projectDescription),
                notes: ['AI service unavailable, using fallback tasks'],
                problems: ['Could not connect to AI service: ' . $e->getMessage()],
                rawResponse: null
            );
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
     * Clean AI response content to extract JSON from potential markdown or formatting.
     */
    protected function cleanResponseContent(string $content): string
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*\n?/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);

        // Remove any leading/trailing whitespace
        $content = trim($content);

        // If content starts with explanatory text before JSON, try to extract just the JSON
        if (!str_starts_with($content, '{')) {
            // Look for the first { and take everything from there
            $jsonStart = strpos($content, '{');
            if ($jsonStart !== false) {
                $content = substr($content, $jsonStart);
            }
        }

        // If content ends with explanatory text after JSON, try to extract just the JSON
        if (!str_ends_with(rtrim($content), '}')) {
            // Look for the last } and take everything up to there
            $jsonEnd = strrpos($content, '}');
            if ($jsonEnd !== false) {
                $content = substr($content, 0, $jsonEnd + 1);
            }
        }

        return $content;
    }

    /**
     * Build system prompt with schema information.
     */
    protected function buildSystemPromptWithSchema(string $basePrompt, array $schema): string
    {
        $enhancedPrompt = $basePrompt;

        if (!empty($schema)) {
            $enhancedPrompt .= "\n\nYou must respond with a valid JSON object that includes:";
            $enhancedPrompt .= "\n- 'tasks': An array of task objects following the provided schema";
            $enhancedPrompt .= "\n- 'summary': A brief summary of your analysis and approach";
            $enhancedPrompt .= "\n- 'notes': Any important observations or clarifications";
            $enhancedPrompt .= "\n- 'problems': Any issues or concerns you identify with the project description";
            $enhancedPrompt .= "\n- 'suggestions': Recommendations for improving the project or task breakdown";
            $enhancedPrompt .= "\n\nBe thorough in your analysis and provide valuable insights in the communication fields.";
        }

        return $enhancedPrompt;
    }

    /**
     * Parse AI response into structured task response.
     */
    protected function parseTaskResponse(AIResponse $response, string $projectDescription): AITaskResponse
    {
        try {
            // Clean the response content first
            $content = $this->cleanResponseContent($response->getContent());

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Response content is not valid JSON: ' . json_last_error_msg());
            }

            // Extract tasks, title, and communication
            $tasks = $data['tasks'] ?? [];
            $projectTitle = $data['project_title'] ?? null;
            $summary = $data['summary'] ?? null;
            $notes = $data['notes'] ?? [];
            $problems = $data['problems'] ?? [];
            $suggestions = $data['suggestions'] ?? [];

            // Ensure notes is an array
            if (is_string($notes)) {
                $notes = [$notes];
            }

            // Ensure problems and suggestions are arrays
            if (is_string($problems)) {
                $problems = [$problems];
            }
            if (is_string($suggestions)) {
                $suggestions = [$suggestions];
            }

            // Validate tasks structure
            $validatedTasks = $this->validateTasks($tasks);

            return AITaskResponse::success(
                tasks: $validatedTasks,
                projectTitle: $projectTitle,
                notes: $notes,
                summary: $summary,
                problems: $problems,
                suggestions: $suggestions,
                rawResponse: $response
            );

        } catch (\InvalidArgumentException $e) {
            // If JSON parsing fails, log the actual response for debugging
            $content = $response->getContent();

            $this->logError('parseTaskResponse', 'JSON parsing failed. Response content: ' . substr($content, 0, 500) . '...');

            return AITaskResponse::success(
                tasks: $this->createFallbackTasks($projectDescription),
                projectTitle: $this->generateFallbackTitle($projectDescription),
                notes: ['AI response was not in expected JSON format'],
                problems: [
                    'Could not parse AI response: ' . $e->getMessage(),
                    'AI returned: ' . substr($content, 0, 200) . '...'
                ],
                suggestions: [
                    'The AI model may need clearer instructions about JSON format',
                    'Consider using a different model or adjusting the prompt'
                ],
                rawResponse: $response
            );
        }
    }

    /**
     * Validate and clean task structure.
     */
    protected function validateTasks(array $tasks): array
    {
        $validatedTasks = [];

        foreach ($tasks as $task) {
            if (is_array($task) && isset($task['title'])) {
                $priority = $task['priority'] ?? 'medium';
                $status = $task['status'] ?? 'pending';

                $validatedTasks[] = [
                    'title' => $task['title'] ?? 'Untitled Task',
                    'description' => $task['description'] ?? '',
                    'priority' => in_array($priority, ['low', 'medium', 'high'])
                        ? $priority
                        : 'medium',
                    'status' => in_array($status, ['pending', 'in_progress', 'completed'])
                        ? $status
                        : 'pending',
                    'subtasks' => $task['subtasks'] ?? []
                ];
            }
        }

        return $validatedTasks;
    }

    /**
     * Generate a fallback title based on project description.
     */
    protected function generateFallbackTitle(string $projectDescription): string
    {
        // Extract key words from description and create a simple title
        $words = str_word_count($projectDescription, 1);
        $keywords = array_slice($words, 0, 4); // Take first 4 words

        // Clean and capitalize
        $title = implode(' ', array_map('ucfirst', array_map('strtolower', $keywords)));

        // Add "Project" if it doesn't seem to be included
        if (!str_contains(strtolower($title), 'project') && !str_contains(strtolower($title), 'app') && !str_contains(strtolower($title), 'system')) {
            $title .= ' Project';
        }

        // Limit length
        return substr($title, 0, 50);
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
