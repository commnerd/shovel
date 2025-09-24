<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CurationPrompt>
 */
class CurationPromptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'project_id' => \App\Models\Project::factory(),
            'prompt_text' => $this->generateSamplePrompt(),
            'ai_provider' => $this->faker->randomElement(['openai', 'cerebras', 'anthropic']),
            'ai_model' => $this->faker->randomElement(['gpt-4', 'llama-4-maverick-17b-128e-instruct', 'claude-3']),
            'is_organization_user' => $this->faker->boolean(30), // 30% chance of being organization user
            'task_count' => $this->faker->numberBetween(1, 20),
        ];
    }

    /**
     * Generate a sample curation prompt.
     */
    private function generateSamplePrompt(): string
    {
        $prompts = [
            "Please analyze the following project and tasks to provide daily curation suggestions:\n\n**Project:** Sample Project\n**Type:** iterative\n**User:** John Doe\n**Current Date:** 2025-09-24\n\n**Current Tasks:**\n- ID: 1 - Implement user authentication (pending)\n- ID: 2 - Design database schema (pending)\n\nPlease provide suggestions for:\n1. Priority tasks to focus on today\n2. Tasks that might be overdue or at risk",

            "Please analyze the following project and tasks to provide daily curation suggestions:\n\n**Project:** E-commerce Platform\n**Type:** finite\n**User:** Jane Smith\n**Current Date:** 2025-09-24\n**User Type:** Organization member (only suggest unassigned tasks)\n\n**IMPORTANT:** This user is in an organization. Only suggest tasks that are NOT already assigned to someone today.\n\n**Current Tasks:**\n- ID: 3 - Set up payment processing (pending)\n- ID: 4 - Create product catalog (pending)\n\nPlease provide suggestions for:\n1. Unassigned priority tasks to focus on today\n2. Unassigned tasks that might be overdue or at risk",

            "Please analyze the following project and tasks to provide daily curation suggestions:\n\n**Project:** Mobile App Development\n**Type:** iterative\n**User:** Mike Johnson\n**Current Date:** 2025-09-24\n\n**Current Tasks:**\n- ID: 5 - Implement push notifications (in_progress)\n- ID: 6 - Add user profile features (pending)\n\nPlease provide suggestions for:\n1. Priority tasks to focus on today\n2. Tasks that might be overdue or at risk"
        ];

        return $this->faker->randomElement($prompts);
    }

    /**
     * Create a curation prompt for a specific user.
     */
    public function forUser(\App\Models\User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a curation prompt for a specific project.
     */
    public function forProject(\App\Models\Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Create a curation prompt for an organization user.
     */
    public function organizationUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_organization_user' => true,
        ]);
    }

    /**
     * Create a curation prompt for an individual user.
     */
    public function individualUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_organization_user' => false,
        ]);
    }

    /**
     * Create a curation prompt with a specific AI provider.
     */
    public function withAIProvider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_provider' => $provider,
        ]);
    }

    /**
     * Create a curation prompt with a specific task count.
     */
    public function withTaskCount(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'task_count' => $count,
        ]);
    }

    /**
     * Create a curation prompt with a custom prompt text.
     */
    public function withPrompt(string $prompt): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_text' => $prompt,
        ]);
    }
}
