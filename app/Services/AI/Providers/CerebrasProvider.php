<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\AIResponse;
use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\AIPromptService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CerebrasProvider implements AIProviderInterface
{
    protected array $config;
    protected ?AIPromptService $promptService = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->promptService = new AIPromptService();
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
        if (!$this->promptService) {
            $this->promptService = new AIPromptService();
        }

        $systemPrompt = $this->promptService->buildTaskGenerationSystemPrompt();
        $userPrompt = $this->promptService->buildTaskGenerationUserPrompt($projectDescription, $options);

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

            return $this->parseTaskResponse($response, $projectDescription, $options);
        } catch (\Exception $e) {
            $this->logError('generateTasks', $e->getMessage());

            // Return fallback response
            return AITaskResponse::success(
                tasks: $this->createFallbackTasks($projectDescription),
                projectTitle: $this->generateFallbackTitle($projectDescription),
                notes: $this->addServiceInfoToNotes(['AI service unavailable, using fallback tasks'], null),
                problems: ['Could not connect to AI service: '.$e->getMessage()],
                rawResponse: null
            );
        }
    }

    /**
     * Analyze a project and provide insights.
     */
    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
    {
        $prompts = [
            'system' => 'You are an expert project analyst. Analyze project descriptions and provide strategic insights.',
            'user' => 'Please analyze this project: {description}. Provide insights on scope, complexity, risks, and recommendations.'
        ];

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
        $prompts = [
            'system' => 'You are a task optimization expert. Analyze existing tasks and suggest improvements.',
            'user' => 'Please analyze these tasks and suggest improvements: {tasks}. Focus on clarity, completeness, and actionability.'
        ];
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
                'type' => 'text',
            ];
        }
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return 'cerebras';
    }

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Get provider configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Make an HTTP request to the Cerebras API.
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        $client = $this->buildHttpClient();

        $response = $client->post($endpoint, $data);

        if ($response->failed()) {
            throw new \Exception('Cerebras API request failed: '.$response->body());
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
                'Authorization' => 'Bearer '.$this->config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout']);
    }

    /**
     * Log AI request for monitoring and debugging.
     */
    protected function logRequest(string $operation, array $input, string $output, float $responseTime, ?int $tokensUsed): void
    {
        if (! config('ai.logging.enabled')) {
            return;
        }

        Log::channel(config('ai.logging.channel', 'daily'))->info('AI Request', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'model' => $this->config['model'] ?? 'unknown',
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
        if (! config('ai.logging.log_errors')) {
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
        if (! str_starts_with($content, '{')) {
            // Look for the first { and take everything from there
            $jsonStart = strpos($content, '{');
            if ($jsonStart !== false) {
                $content = substr($content, $jsonStart);
            }
        }

        // If content ends with explanatory text after JSON, try to extract just the JSON
        if (! str_ends_with(rtrim($content), '}')) {
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

        if (! empty($schema)) {
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
    protected function parseTaskResponse(AIResponse $response, string $projectDescription, array $options = []): AITaskResponse
    {
        try {
            // Clean the response content first
            $content = $this->cleanResponseContent($response->getContent());

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If JSON parsing fails, try to parse as text list
                $this->logError('json_parse_failed', 'Response content is not valid JSON: '.json_last_error_msg().'. Content: '.substr($content, 0, 200));

                // Try to parse as text list similar to OpenAIProvider
                $tasks = $this->parseTextListToTasks($content);
                if (!empty($tasks)) {
                    return AITaskResponse::success(
                        tasks: $tasks,
                        projectTitle: null,
                        notes: ["Generated using Cerebras (text parsing fallback)"],
                        summary: "Task breakdown for: {$projectDescription}",
                        problems: [],
                        suggestions: [],
                        rawResponse: $response
                    );
                }

                throw new \InvalidArgumentException('Response content is not valid JSON and text parsing failed: '.json_last_error_msg());
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
            $projectDueDate = $options['project_due_date'] ?? null;
            $validatedTasks = $this->validateTasks($tasks, $projectDueDate);

            // Add AI service and model information to notes
            $enhancedNotes = $this->addServiceInfoToNotes($notes, $response);

            return AITaskResponse::success(
                tasks: $validatedTasks,
                projectTitle: $projectTitle,
                notes: $enhancedNotes,
                summary: $summary,
                problems: $problems,
                suggestions: $suggestions,
                rawResponse: $response
            );

        } catch (\InvalidArgumentException $e) {
            // If JSON parsing fails, log the actual response for debugging
            $content = $response->getContent();

            $this->logError('parseTaskResponse', 'JSON parsing failed. Response content: '.substr($content, 0, 500).'...');

            return AITaskResponse::success(
                tasks: $this->createFallbackTasks($projectDescription),
                projectTitle: $this->generateFallbackTitle($projectDescription),
                notes: $this->addServiceInfoToNotes(['AI response was not in expected JSON format'], $response),
                problems: [
                    'Could not parse AI response: '.$e->getMessage(),
                    'AI returned: '.substr($content, 0, 200).'...',
                ],
                suggestions: [
                    'The AI model may need clearer instructions about JSON format',
                    'Consider using a different model or adjusting the prompt',
                ],
                rawResponse: $response
            );
        }
    }

    /**
     * Validate and clean task structure.
     */
    protected function validateTasks(array $tasks, ?string $projectDueDate = null): array
    {
        $validatedTasks = [];

        foreach ($tasks as $task) {
            if (is_array($task) && isset($task['title'])) {
                $status = $task['status'] ?? 'pending';

                // If task doesn't have a due date but project does, calculate a reasonable due date
                $dueDate = $task['due_date'] ?? null;
                if (!$dueDate && $projectDueDate) {
                    $dueDate = $this->calculateTaskDueDateFromProject($projectDueDate, $task);
                }

                $validatedTasks[] = [
                    'title' => $task['title'] ?? 'Untitled Task',
                    'description' => $task['description'] ?? '',
                    'status' => in_array($status, ['pending', 'in_progress', 'completed'])
                        ? $status
                        : 'pending',
                    'due_date' => $dueDate,
                    'size' => $this->extractTaskSize($task),
                    'initial_story_points' => $task['initial_story_points'] ?? null,
                    'current_story_points' => $task['current_story_points'] ?? null,
                    'story_points_change_count' => $task['story_points_change_count'] ?? 0,
                    'subtasks' => $task['subtasks'] ?? [],
                ];
            }
        }

        return $validatedTasks;
    }

    /**
     * Extract task size from AI response or generate fallback.
     */
    protected function extractTaskSize(array $task): ?string
    {
        // Check if size is explicitly provided (accept both uppercase and lowercase)
        if (isset($task['size'])) {
            $size = strtolower($task['size']);
            if (in_array($size, ['xs', 's', 'm', 'l', 'xl'])) {
                return $size; // Return lowercase for database storage
            }
        }

        // Only generate fallback size for top-level tasks (when no parent_id is implied)
        // For subtasks, return null as they should use story points instead
        if (isset($task['parent_id']) || isset($task['is_subtask']) || isset($task['current_story_points'])) {
            return null;
        }

        // Generate fallback size based on task content
        return $this->generateFallbackSize($task);
    }

    /**
     * Generate fallback size based on task content.
     */
    protected function generateFallbackSize(array $task): string
    {
        $title = strtolower($task['title'] ?? '');
        $description = strtolower($task['description'] ?? '');

        $text = $title . ' ' . $description;

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
        if (! str_contains(strtolower($title), 'project') && ! str_contains(strtolower($title), 'app') && ! str_contains(strtolower($title), 'system')) {
            $title .= ' Project';
        }

        // Limit length
        return substr($title, 0, 50);
    }

    /**
     * Break down a task into subtasks with project context.
     */
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

            if (! $response->isSuccessful()) {
                $this->logError('task_breakdown', $response->getErrorMessage() ?? 'Unknown error');

                return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, $context['project']['due_date'] ?? null);
            }

            $content = $this->cleanResponseContent($response->getContent());
            $this->logRequest('task_breakdown', [$taskTitle, $taskDescription], $content, 0, null);

            // Extract project due date from context if available
            $breakdownOptions = ['project_due_date' => $context['project']['due_date'] ?? null];
            $parsedResponse = $this->parseTaskResponse($response, $taskTitle, $breakdownOptions);

            return $parsedResponse ?: $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, $context['project']['due_date'] ?? null);

        } catch (\Exception $e) {
            $this->logError('task_breakdown', $e->getMessage());

            return $this->createFallbackTaskBreakdown($taskTitle, $taskDescription, $context['project']['due_date'] ?? null);
        }
    }



    /**
     * Create fallback task breakdown when AI fails.
     */
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
                        'size' => $this->extractTaskSize(['title' => $title]),
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
                        'size' => $this->extractTaskSize(['title' => $cleanTitle]),
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
                        'size' => $this->extractTaskSize(['title' => trim($line)]),
                    ];

                    if (count($tasks) >= 5) break; // Limit to reasonable number
                }
            }
        }

        return $tasks;
    }

    protected function createFallbackTaskBreakdown(string $taskTitle, string $taskDescription, ?string $projectDueDate = null): AITaskResponse
    {
        $fallbackTasks = [
            [
                'title' => 'Research & Planning',
                'description' => "Research requirements and plan approach for: {$taskTitle}",
                'status' => 'pending',
                'due_date' => $projectDueDate ? $this->calculateTaskDueDateFromProject($projectDueDate, ['title' => 'Research & Planning']) : null,
            ],
            [
                'title' => 'Implementation',
                'description' => "Implement the main functionality for: {$taskTitle}",
                'status' => 'pending',
                'due_date' => $projectDueDate ? $this->calculateTaskDueDateFromProject($projectDueDate, ['title' => 'Implementation']) : null,
            ],
            [
                'title' => 'Testing & Validation',
                'description' => 'Test and validate the implementation',
                'status' => 'pending',
                'due_date' => $projectDueDate ? $this->calculateTaskDueDateFromProject($projectDueDate, ['title' => 'Testing & Validation']) : null,
            ],
        ];

        return AITaskResponse::success(
            $fallbackTasks,
            null,
            $this->addServiceInfoToNotes(['AI task breakdown failed, using fallback subtasks'], null)
        );
    }

    /**
     * Create fallback tasks when AI parsing fails.
     */
    public function createFallbackTasks(string $projectDescription): array
    {
        return [
            [
                'title' => 'Project Planning & Setup',
                'description' => 'Set up project structure and define requirements based on: '.$projectDescription,
                'status' => 'pending',
                'subtasks' => [],
            ],
            [
                'title' => 'Core Development',
                'description' => 'Implement main functionality and features',
                'status' => 'pending',
                'subtasks' => [],
            ],
            [
                'title' => 'Testing & Quality Assurance',
                'description' => 'Write tests and ensure code quality',
                'status' => 'pending',
                'subtasks' => [],
            ],
            [
                'title' => 'Documentation & Deployment',
                'description' => 'Create documentation and deploy the project',
                'status' => 'pending',
                'subtasks' => [],
            ],
        ];
    }

    /**
     * Calculate a reasonable due date for a task based on project due date.
     */
    protected function calculateTaskDueDateFromProject(string $projectDueDate, array $task): ?string
    {
        try {
            $projectDate = \Carbon\Carbon::parse($projectDueDate);
            $now = now();

            // If project due date is in the past, don't set a due date
            if ($projectDate->isPast()) {
                return null;
            }

            // Calculate task due date based on project timeline (60% into timeline)
            $daysFromNow = $now->diffInDays($projectDate);
            $dueDateOffset = $daysFromNow * 0.6;

            // Ensure minimum 1 day and maximum is project due date
            $dueDateOffset = max(1, min($dueDateOffset, $daysFromNow));
            $taskDueDate = $now->copy()->addDays(round($dueDateOffset));

            // Don't exceed project due date
            if ($taskDueDate->gt($projectDate)) {
                $taskDueDate = $projectDate->copy()->subDay(); // Due day before project
            }


            return $taskDueDate->format('Y-m-d');
        } catch (\Exception $e) {
            // If date parsing fails, return null
            return null;
        }
    }

    /**
     * Add AI service and model information to notes.
     */
    protected function addServiceInfoToNotes(array $notes, ?AIResponse $response): array
    {
        $serviceInfo = "Generated by: {$this->getName()}";

        // Add model information if available
        if ($response && $response->getModel()) {
            $serviceInfo .= " ({$response->getModel()})";
        } elseif (!empty($this->config['model'])) {
            $serviceInfo .= " ({$this->config['model']})";
        }

        // Add the service info to the beginning of the notes
        array_unshift($notes, $serviceInfo);

        return $notes;
    }


    /**
     * Build messages array for chat completion (used by controller for prompt viewing).
     */
    protected function buildMessages(string $systemPrompt, string $userPrompt): array
    {
        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

}
