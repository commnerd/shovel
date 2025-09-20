<?php

namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    /**
     * Generate a chat completion.
     *
     * @param  array  $messages  Array of messages with 'role' and 'content'
     * @param  array  $options  Additional options like model, temperature, etc.
     */
    public function chat(array $messages, array $options = []): AIResponse;

    /**
     * Generate tasks based on a project description with schema validation.
     *
     * Note: Implementations should include AI service and model information in the response notes.
     *
     * @param  array  $schema  The expected JSON schema for the response
     */
    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): AITaskResponse;

    /**
     * Break down a task into subtasks with project context.
     *
     * Note: Implementations should include AI service and model information in the response notes.
     *
     * @param  array  $context  Project and task context information
     */
    public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): AITaskResponse;

    /**
     * Analyze a project and provide insights.
     */
    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string;

    /**
     * Suggest task improvements or next steps.
     */
    public function suggestTaskImprovements(array $tasks, array $options = []): array;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Get provider configuration.
     */
    public function getConfig(): array;
}
