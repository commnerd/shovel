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
     * Generate tasks based on a project description.
     *
     * @param string $projectDescription
     * @param array $options
     * @return array
     */
    public function generateTasks(string $projectDescription, array $options = []): array;

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
