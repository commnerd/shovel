<?php

namespace App\Services\AI;

class AIPromptService
{
    /**
     * Build the system prompt for task generation.
     */
    public function buildTaskGenerationSystemPrompt(): string
    {
        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');

        $basePrompt = 'You are an expert project manager and task breakdown specialist. Your role is to analyze project descriptions and generate comprehensive, actionable task breakdowns. You must respond with valid JSON only - no explanations, no markdown, no code blocks.';

        return $basePrompt . "\n\n" .
            "Current Date and Time: {$currentDateTime}\n\n" .
            "Your response must be a valid JSON object with the following structure:\n" .
            "{\n" .
            "  \"title\": \"Compelling Project Title\",\n" .
            "  \"tasks\": [\n" .
            "    {\n" .
            "      \"title\": \"Task Title\",\n" .
            "      \"description\": \"Detailed task description\",\n" .
            "      \"status\": \"pending\",\n" .
            "      \"due_date\": \"YYYY-MM-DD\" (optional),\n" .
            "      \"size\": \"xs|s|m|l|xl\" (for iterative projects),\n" .
            "      \"initial_story_points\": number (for iterative projects),\n" .
            "      \"current_story_points\": number (for iterative projects),\n" .
            "      \"story_points_change_count\": 0 (for iterative projects)\n" .
            "    }\n" .
            "  ]\n" .
            "}\n\n" .
            "For iterative projects, use T-shirt sizes (xs, s, m, l, xl) and Fibonacci story points (1, 2, 3, 5, 8, 13, 21).\n" .
            "For finite projects, omit size and story points fields.\n" .
            "Ensure all tasks are actionable, specific, and well-described.";
    }

    /**
     * Build the user prompt for task generation.
     */
    public function buildTaskGenerationUserPrompt(string $projectDescription, array $aiOptions = []): string
    {
        $userPrompt = 'Please analyze this project description and generate a comprehensive task breakdown: ' . $projectDescription . '

CRITICAL: You must respond with ONLY a valid JSON object in this exact format:
{
  "title": "Compelling Project Title",
  "tasks": [
    {
      "title": "Task Title",
      "description": "Detailed task description",
      "status": "pending",
      "due_date": "YYYY-MM-DD" (optional),
      "size": "xs|s|m|l|xl" (for iterative projects only),
      "initial_story_points": number (for iterative projects only),
      "current_story_points": number (for iterative projects only),
      "story_points_change_count": 0 (for iterative projects only)
    }
  ]
}

IMPORTANT RULES:
- Respond with ONLY the JSON object, no explanations or markdown
- For iterative projects: Use T-shirt sizes (xs, s, m, l, xl) and Fibonacci story points (1, 2, 3, 5, 8, 13, 21)
- For finite projects: Omit size and story points fields entirely
- Ensure all tasks are actionable, specific, and well-described
- Include realistic due dates when appropriate
- Generate 5-15 tasks depending on project complexity';

        if (!empty($aiOptions['user_feedback'])) {
            $userPrompt .= "\n\nUser Feedback: " . $aiOptions['user_feedback'];
        }

        return $userPrompt;
    }

    /**
     * Build the system prompt for task breakdown.
     */
    public function buildTaskBreakdownSystemPrompt(): string
    {
        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');

        $basePrompt = 'You are an expert project manager and task breakdown specialist. Your job is to analyze a given task and break it down into smaller, actionable subtasks. Consider the project context, existing tasks, and completion statuses to provide relevant and practical subtask suggestions.

You must respond with valid JSON only - no explanations, no markdown, no code blocks.';

        return $basePrompt . "\n\n" .
            "Current Date and Time: {$currentDateTime}\n\n" .
            "Your response must be a valid JSON object with the following structure:\n" .
            "{\n" .
            "  \"subtasks\": [\n" .
            "    {\n" .
            "      \"title\": \"Subtask Title\",\n" .
            "      \"description\": \"Detailed subtask description\",\n" .
            "      \"status\": \"pending\",\n" .
            "      \"due_date\": \"YYYY-MM-DD\" (optional),\n" .
            "      \"initial_story_points\": number (for iterative projects),\n" .
            "      \"current_story_points\": number (for iterative projects),\n" .
            "      \"story_points_change_count\": 0 (for iterative projects)\n" .
            "    }\n" .
            "  ],\n" .
            "  \"summary\": \"Brief summary of the breakdown approach\",\n" .
            "  \"notes\": [\"Additional notes or considerations\"],\n" .
            "  \"problems\": [\"Potential issues or challenges\"],\n" .
            "  \"suggestions\": [\"Recommendations for implementation\"]\n" .
            "}\n\n" .
            "For iterative projects, use Fibonacci story points (1, 2, 3, 5, 8, 13, 21).\n" .
            "For finite projects, omit story points fields.\n" .
            "Ensure all subtasks are actionable, specific, and well-described.";
    }

    /**
     * Build the user prompt for task breakdown.
     */
    public function buildTaskBreakdownUserPrompt(string $taskTitle, string $taskDescription, array $context): string
    {
        $basePrompt = 'Please break down the following task into smaller, actionable subtasks:';

        $currentDateTime = now()->format('l, F j, Y \a\t g:i A T');
        $prompt = $basePrompt . "\n\n";
        $prompt .= "**Task to Break Down:**\n";
        $prompt .= "Title: {$taskTitle}\n";
        $prompt .= "Description: {$taskDescription}\n\n";

        // Add project context
        if (!empty($context['project_context'])) {
            $project = $context['project_context'];
            $prompt .= "**Project Context:**\n";
            $prompt .= "Project: {$project['title']}\n";
            $prompt .= "Description: {$project['description']}\n";
            $prompt .= "Type: {$project['project_type']}\n";
            $prompt .= "Total Tasks: {$project['total_tasks']}\n";
            $prompt .= "Completed Tasks: {$project['completed_tasks']}\n\n";
        }

        // Add parent task context with story point constraints
        if (!empty($context['parent_task'])) {
            $parent = $context['parent_task'];
            $prompt .= "**Parent Task:**\n";
            $prompt .= "Title: {$parent['title']}\n";
            if (!empty($parent['size'])) {
                $maxPoints = $this->getMaxStoryPointsForSize($parent['size']);
                if ($maxPoints) {
                    $prompt .= "Size: {$parent['size']}\n";
                    $prompt .= "\n**🚨 CRITICAL CONSTRAINT - MUST FOLLOW EXACTLY 🚨**\n";
                    $prompt .= "The parent task has a T-shirt size of '{$parent['size']}'. ";
                    $prompt .= "**ABSOLUTE RULE: NO subtask can have {$maxPoints} or more story points.**\n";
                    $prompt .= "**MAXIMUM ALLOWED: " . ($maxPoints - 1) . " story points per subtask.**\n";
                    $prompt .= "**VALID STORY POINTS FOR SUBTASKS: 1, 2" . ($maxPoints > 3 ? ", 3" : "") . ($maxPoints > 5 ? ", 5" : "") . ($maxPoints > 8 ? ", 8" : "") . "**\n";
                    $prompt .= "**VIOLATION OF THIS RULE WILL RESULT IN REJECTION.**\n";
                    $prompt .= "Double-check every subtask's story points before responding.\n\n";
                }
            }
        }

        // Add existing tasks sample
        if (!empty($context['sample_existing_tasks']) && is_array($context['sample_existing_tasks'])) {
            $prompt .= "**Sample Existing Tasks:**\n";
            foreach (array_slice($context['sample_existing_tasks'], 0, 5) as $task) {
                $prompt .= "- {$task['title']} ({$task['status']})\n";
            }
            $prompt .= "\n";
        }

        // Add user feedback if provided
        if (!empty($context['user_feedback'])) {
            $prompt .= "**User Feedback:**\n";
            $prompt .= $context['user_feedback'] . "\n\n";
        }

        $prompt .= "**Instructions:**\n";
        $prompt .= "1. Break down the task into 3-8 smaller, actionable subtasks\n";
        $prompt .= "2. Each subtask should be specific and measurable\n";
        $prompt .= "3. Consider dependencies and logical order\n";
        $prompt .= "4. Include realistic due dates when appropriate\n";
        $prompt .= "5. For iterative projects, assign appropriate story points using Fibonacci sequence\n";
        $prompt .= "6. Provide a brief summary of your approach\n";
        $prompt .= "7. Include any notes, problems, or suggestions\n\n";

        $prompt .= "**Response Format:**\n";
        $prompt .= "Respond with ONLY a valid JSON object in this exact format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"subtasks\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"title\": \"Subtask Title\",\n";
        $prompt .= "      \"description\": \"Detailed subtask description\",\n";
        $prompt .= "      \"status\": \"pending\",\n";
        $prompt .= "      \"due_date\": \"YYYY-MM-DD\" (optional),\n";
        $prompt .= "      \"initial_story_points\": number (for iterative projects),\n";
        $prompt .= "      \"current_story_points\": number (for iterative projects),\n";
        $prompt .= "      \"story_points_change_count\": 0 (for iterative projects)\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"summary\": \"Brief summary of the breakdown approach\",\n";
        $prompt .= "  \"notes\": [\"Additional notes or considerations\"],\n";
        $prompt .= "  \"problems\": [\"Potential issues or challenges\"],\n";
        $prompt .= "  \"suggestions\": [\"Recommendations for implementation\"]\n";
        $prompt .= "}\n\n";

        $prompt .= "CRITICAL: Respond with ONLY the JSON object, no explanations or markdown formatting.";

        return $prompt;
    }

    /**
     * Get the maximum story points allowed for a given T-shirt size.
     */
    public function getMaxStoryPointsForSize(string $size): ?int
    {
        $sizeToMaxPoints = [
            'xs' => 2,   // Extra Small: max 2 story points (smaller than 3)
            's' => 3,    // Small: max 3 story points (smaller than 5)
            'm' => 5,    // Medium: max 5 story points (smaller than 8)
            'l' => 8,    // Large: max 8 story points (smaller than 13)
            'xl' => 13,  // Extra Large: max 13 story points (smaller than 21)
        ];

        return $sizeToMaxPoints[$size] ?? null;
    }
}

