<?php

namespace Tests\Browser;

use App\Services\AI\Contracts\AITaskResponse;
use App\Services\AI\Contracts\AIResponse;
use Illuminate\Support\Facades\App;

trait MocksAIServices
{
    /**
     * Mock AI services to prevent real API calls during testing.
     */
    protected function mockAIServices(): void
    {
        // Mock the AI facade
        $this->app->bind('ai', function () {
            return new class {
                public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse
                {
                    // Generate mock tasks based on the project description
                    $mockTasks = $this->generateMockTasks($projectDescription);
                    
                    return AITaskResponse::success(
                        tasks: $mockTasks,
                        projectTitle: $this->generateMockProjectTitle($projectDescription),
                        notes: ['Mock AI response for testing'],
                        summary: 'Mock project analysis completed',
                        problems: [],
                        suggestions: ['Consider breaking down complex tasks', 'Add more detailed requirements']
                    );
                }

                public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): AITaskResponse
                {
                    // Generate mock subtasks
                    $mockSubtasks = $this->generateMockSubtasks($taskTitle, $taskDescription);
                    
                    return AITaskResponse::success(
                        tasks: $mockSubtasks,
                        projectTitle: $taskTitle,
                        notes: ['Mock task breakdown for testing'],
                        summary: 'Mock task analysis completed'
                    );
                }

                public function chat(array $messages, array $options = []): AIResponse
                {
                    return new class implements AIResponse {
                        public function getContent(): string
                        {
                            return 'Mock AI chat response for testing';
                        }

                        public function getUsage(): array
                        {
                            return ['tokens' => 100, 'cost' => 0.001];
                        }

                        public function toArray(): array
                        {
                            return [
                                'content' => $this->getContent(),
                                'usage' => $this->getUsage()
                            ];
                        }
                    };
                }

                public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
                {
                    return 'Mock project analysis: This is a test project that requires careful planning and execution.';
                }

                public function suggestTaskImprovements(array $tasks, array $options = []): array
                {
                    return [
                        'Consider adding more detailed acceptance criteria',
                        'Break down large tasks into smaller, manageable pieces',
                        'Add time estimates for better planning'
                    ];
                }

                public function hasConfiguredProvider(): bool
                {
                    return true;
                }

                public function getAvailableProviders(): array
                {
                    return ['cerebras', 'openai', 'anthropic'];
                }

                public function testProvider(string $name = null): array
                {
                    return [
                        'success' => true,
                        'response_time' => 0.1,
                        'message' => 'Mock provider test successful'
                    ];
                }

                public function provider(string $name = null): self
                {
                    return $this;
                }

                private function generateMockTasks(string $description): array
                {
                    $baseTasks = [
                        [
                            'title' => 'Project Setup & Planning',
                            'description' => 'Set up project structure and define requirements based on: ' . $description,
                            'status' => 'pending',
                            'size' => 'm',
                            'due_date' => null,
                            'sort_order' => 1,
                        ],
                        [
                            'title' => 'Core Feature Development',
                            'description' => 'Implement main functionality based on project description',
                            'status' => 'pending',
                            'size' => 'l',
                            'due_date' => null,
                            'sort_order' => 2,
                        ],
                        [
                            'title' => 'Testing & Quality Assurance',
                            'description' => 'Write tests and ensure code quality',
                            'status' => 'pending',
                            'size' => 'm',
                            'due_date' => null,
                            'sort_order' => 3,
                        ],
                        [
                            'title' => 'Documentation & Deployment',
                            'description' => 'Create documentation and deploy the project',
                            'status' => 'pending',
                            'size' => 's',
                            'due_date' => null,
                            'sort_order' => 4,
                        ],
                    ];

                    // Add a task based on the description
                    if (stripos($description, 'authentication') !== false) {
                        $baseTasks[] = [
                            'title' => 'Implement user authentication system',
                            'description' => 'Build secure user authentication and authorization system',
                            'status' => 'pending',
                            'size' => 'l',
                            'due_date' => null,
                            'sort_order' => 5,
                        ];
                    }

                    if (stripos($description, 'management') !== false) {
                        $baseTasks[] = [
                            'title' => 'Build user management system',
                            'description' => 'Create user management interface and functionality',
                            'status' => 'pending',
                            'size' => 'm',
                            'due_date' => null,
                            'sort_order' => 6,
                        ];
                    }

                    if (stripos($description, 'integration') !== false) {
                        $baseTasks[] = [
                            'title' => 'Complex system integration task',
                            'description' => 'Integrate multiple systems and ensure compatibility',
                            'status' => 'pending',
                            'size' => 'xl',
                            'due_date' => null,
                            'sort_order' => 7,
                        ];
                    }

                    return $baseTasks;
                }

                private function generateMockSubtasks(string $taskTitle, string $taskDescription): array
                {
                    return [
                        [
                            'title' => 'Analyze requirements',
                            'description' => 'Analyze and document requirements for: ' . $taskTitle,
                            'status' => 'pending',
                            'size' => 's',
                            'due_date' => null,
                            'sort_order' => 1,
                        ],
                        [
                            'title' => 'Design solution',
                            'description' => 'Design the technical solution for: ' . $taskTitle,
                            'status' => 'pending',
                            'size' => 'm',
                            'due_date' => null,
                            'sort_order' => 2,
                        ],
                        [
                            'title' => 'Implement core functionality',
                            'description' => 'Implement the main functionality for: ' . $taskTitle,
                            'status' => 'pending',
                            'size' => 'l',
                            'due_date' => null,
                            'sort_order' => 3,
                        ],
                        [
                            'title' => 'Test and validate',
                            'description' => 'Test the implementation and validate against requirements',
                            'status' => 'pending',
                            'size' => 'm',
                            'due_date' => null,
                            'sort_order' => 4,
                        ],
                    ];
                }

                private function generateMockProjectTitle(string $description): string
                {
                    $words = str_word_count($description, 1);
                    $firstWords = array_slice($words, 0, 3);
                    return implode(' ', $firstWords) . ' Project';
                }
            };
        });
    }
}
