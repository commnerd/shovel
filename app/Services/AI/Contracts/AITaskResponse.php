<?php

namespace App\Services\AI\Contracts;

class AITaskResponse
{
    public function __construct(
        public readonly array $tasks,
        public readonly ?string $projectTitle = null,
        public readonly array $notes = [],
        public readonly ?string $summary = null,
        public readonly array $problems = [],
        public readonly array $suggestions = [],
        public readonly ?AIResponse $rawResponse = null,
        public readonly bool $success = true,
        public readonly ?string $error = null,
    ) {}

    /**
     * Get the generated tasks.
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get the generated project title.
     */
    public function getProjectTitle(): ?string
    {
        return $this->projectTitle;
    }

    /**
     * Get response notes/communication from AI.
     */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /**
     * Get summary information.
     */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * Get problems identified by AI.
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    /**
     * Get suggestions from AI.
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Get the raw AI response.
     */
    public function getRawResponse(): ?AIResponse
    {
        return $this->rawResponse;
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success && empty($this->error);
    }

    /**
     * Get error message if any.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get task count.
     */
    public function getTaskCount(): int
    {
        return count($this->tasks);
    }

    /**
     * Check if AI provided any communication notes.
     */
    public function hasNotes(): bool
    {
        return !empty($this->notes) || !empty($this->summary) || !empty($this->problems) || !empty($this->suggestions);
    }

    /**
     * Get all communication from AI in a structured format.
     */
    public function getCommunication(): array
    {
        return [
            'summary' => $this->summary,
            'notes' => $this->notes,
            'problems' => $this->problems,
            'suggestions' => $this->suggestions,
        ];
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'project_title' => $this->projectTitle,
            'tasks' => $this->tasks,
            'task_count' => $this->getTaskCount(),
            'communication' => $this->getCommunication(),
            'has_notes' => $this->hasNotes(),
            'error' => $this->error,
            'metadata' => $this->rawResponse?->toArray(),
        ];
    }

    /**
     * Create a successful response.
     */
    public static function success(
        array $tasks,
        ?string $projectTitle = null,
        array|string $notes = [],
        ?string $summary = null,
        array|string $problems = [],
        array|string $suggestions = [],
        ?AIResponse $rawResponse = null
    ): self {
        // Convert strings to arrays for consistency
        $notesArray = is_string($notes) ? [$notes] : $notes;
        $problemsArray = is_string($problems) ? [$problems] : $problems;
        $suggestionsArray = is_string($suggestions) ? [$suggestions] : $suggestions;

        return new self(
            tasks: $tasks,
            projectTitle: $projectTitle,
            notes: $notesArray,
            summary: $summary,
            problems: $problemsArray,
            suggestions: $suggestionsArray,
            rawResponse: $rawResponse,
            success: true
        );
    }

    /**
     * Create a failed response.
     */
    public static function failed(string $error, ?AIResponse $rawResponse = null): self
    {
        return new self(
            tasks: [],
            projectTitle: null,
            rawResponse: $rawResponse,
            success: false,
            error: $error
        );
    }
}
