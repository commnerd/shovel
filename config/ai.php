<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used when
    | no specific provider is requested. You can change this to any of the
    | supported providers listed in the "providers" configuration below.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'cerebrus'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers for your application. Each
    | provider can have different configurations and capabilities. You can
    | add new providers by implementing the AIProviderInterface.
    |
    */

    'providers' => [

        'cerebrus' => [
            'driver' => 'cerebrus',
            'api_key' => env('CEREBRUS_API_KEY'),
            'base_url' => env('CEREBRUS_BASE_URL', 'https://api.cerebras.ai/v1'),
            'model' => env('CEREBRUS_DEFAULT_MODEL', 'llama-4-scout-17b-16e-instruct'),
            'timeout' => env('CEREBRUS_TIMEOUT', 30),
            'max_tokens' => env('CEREBRUS_MAX_TOKENS', 4000),
            'temperature' => env('CEREBRUS_TEMPERATURE', 0.7),
        ],

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
            'timeout' => env('OPENAI_TIMEOUT', 30),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4000),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-sonnet-20240229'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4000),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'model' => env('GEMINI_DEFAULT_MODEL', 'gemini-pro'),
            'timeout' => env('GEMINI_TIMEOUT', 30),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 4000),
            'temperature' => env('GEMINI_TEMPERATURE', 0.7),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | AI Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configure specific AI features and their settings. This allows you to
    | customize how different AI capabilities behave in your application.
    |
    */

    'features' => [

        'task_generation' => [
            'enabled' => env('AI_TASK_GENERATION_ENABLED', true),
            'provider' => env('AI_TASK_GENERATION_PROVIDER', 'cerebrus'),
            'model_override' => env('AI_TASK_GENERATION_MODEL'),
            'max_tasks' => env('AI_TASK_GENERATION_MAX_TASKS', 10),
            'prompt_template' => 'task_generation',
        ],

        'project_analysis' => [
            'enabled' => env('AI_PROJECT_ANALYSIS_ENABLED', true),
            'provider' => env('AI_PROJECT_ANALYSIS_PROVIDER', 'cerebrus'),
            'model_override' => env('AI_PROJECT_ANALYSIS_MODEL'),
            'prompt_template' => 'project_analysis',
        ],

        'task_suggestions' => [
            'enabled' => env('AI_TASK_SUGGESTIONS_ENABLED', true),
            'provider' => env('AI_TASK_SUGGESTIONS_PROVIDER', 'cerebrus'),
            'model_override' => env('AI_TASK_SUGGESTIONS_MODEL'),
            'prompt_template' => 'task_suggestions',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Templates
    |--------------------------------------------------------------------------
    |
    | Define reusable prompt templates for different AI operations. This
    | allows you to maintain consistent prompting across your application
    | and easily update prompts without changing code.
    |
    */

    'prompts' => [

        'task_generation' => [
            'system' => 'You are an expert project manager and task breakdown specialist. Your job is to analyze project descriptions, create compelling project titles, and generate comprehensive, actionable task lists. You must respond with valid JSON only - no additional text, explanations, or markdown formatting.',
            'user' => 'Based on this project description: "{description}", create a compelling project title and detailed task breakdown. Include main tasks and subtasks where appropriate. Consider setup, development, testing, and deployment phases.

CRITICAL: You must respond with ONLY a valid JSON object in this exact format:

{
  "project_title": "E-Commerce Platform Development",
  "tasks": [
    {
      "title": "Setup Development Environment",
      "description": "Install and configure development tools, dependencies, and local environment",
      "priority": "high",
      "status": "pending",
      "subtasks": []
    },
    {
      "title": "Design Database Schema",
      "description": "Create database tables, relationships, and migrations",
      "priority": "high",
      "status": "pending",
      "subtasks": []
    }
  ],
  "summary": "This project requires careful planning with focus on scalable architecture",
  "notes": ["Consider using modern frameworks", "Plan for mobile responsiveness"],
  "problems": ["Timeline might be tight", "Requirements need clarification"],
  "suggestions": ["Break into smaller milestones", "Add buffer time for testing"]
}

Rules:
- project_title should be concise, professional, and capture the essence of the project (3-8 words)
- priority must be exactly "high", "medium", or "low"
- status must be exactly "pending", "in_progress", or "completed"
- Include 3-8 tasks maximum
- Provide helpful summary, notes, problems, and suggestions
- Respond with ONLY the JSON object - no other text',
        ],

        'project_analysis' => [
            'system' => 'You are a senior project consultant who analyzes project requirements and provides strategic insights.',
            'user' => 'Analyze this project: "{description}". Provide insights about scope, complexity, timeline estimates, potential risks, and recommended technologies. Keep the analysis concise but comprehensive.',
        ],

        'task_suggestions' => [
            'system' => 'You are an AI assistant that helps improve task management by suggesting optimizations and next steps.',
            'user' => 'Given these existing tasks: {tasks}, suggest improvements, identify missing tasks, or recommend task prioritization changes. Focus on actionable suggestions.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for AI API calls to prevent excessive usage
    | and manage costs. Different providers may have different limits.
    |
    */

    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'requests_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 60),
        'requests_per_hour' => env('AI_RATE_LIMIT_PER_HOUR', 1000),
        'cache_driver' => env('AI_RATE_LIMIT_CACHE_DRIVER', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure logging and monitoring for AI operations to track usage,
    | performance, and costs across different providers.
    |
    */

    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'channel' => env('AI_LOG_CHANNEL', 'daily'),
        'log_requests' => env('AI_LOG_REQUESTS', true),
        'log_responses' => env('AI_LOG_RESPONSES', false), // Set to false to avoid logging sensitive data
        'log_errors' => env('AI_LOG_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching for AI responses to reduce API calls and improve
    | performance. Be careful with caching as AI responses can vary.
    |
    */

    'caching' => [
        'enabled' => env('AI_CACHING_ENABLED', false),
        'driver' => env('AI_CACHE_DRIVER', 'redis'),
        'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'ai_cache',
    ],

];
