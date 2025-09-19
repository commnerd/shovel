<?php

namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    /**
     * Generate a chat completion.
     *
     * @param array $messages Array of messages with 'role' and 'content'
     * @param array $options Additional options like model, temperature, etc.
     * @return AIResponse
     */
    public function chat(array $messages, array $options = []): AIResponse;

    /**
     * Generate tasks based on a project description with schema validation.
     *
     * @param string $projectDescription
     * @param array $schema The expected JSON schema for the response
     * @param array $options
     * @return AITaskResponse
     */
    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse;

    /**
     * Break down a task into subtasks with project context.
     *
     * @param string $taskTitle
     * @param string $taskDescription
     * @param array $context Project and task context information
     * @param array $options
     * @return AITaskResponse
     */
    public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): AITaskResponse;

    /**
     * Analyze a project and provide insights.
     *
     * @param string $projectDescription
     * @param array $existingTasks
     * @param array $options
     * @return string
     */
    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string;

    /**
     * Suggest task improvements or next steps.
     *
     * @param array $tasks
     * @param array $options
     * @return array
     */
    public function suggestTaskImprovements(array $tasks, array $options = []): array;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if the provider is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get provider configuration.
     *
     * @return array
     */
    public function getConfig(): array;
}
