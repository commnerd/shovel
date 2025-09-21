<?php

namespace App\Services;

class AIConfigurationService
{
    /**
     * Get the available AI providers and their models.
     * This ensures consistency across all forms (system settings, project creation, project edit).
     */
    public static function getAvailableProviders(): array
    {
        return [
            'cerebrus' => [
                'name' => 'Cerebras',
                'description' => 'Fast and efficient AI models',
                'models' => [
                    'gpt-oss-120b' => 'GPT OSS 120B',
                    'llama-3.3-70b' => 'Llama 3.3 70B',
                    'llama-4-maverick-17b-128e-instruct' => 'Llama 4 Maverick 17B (128E Instruct)',
                    'llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B (16E Instruct)',
                    'llama3.1-8b' => 'Llama 3.1 8B',
                    'qwen-3-235b-a22b-instruct-2507' => 'Qwen 3 235B (A22B Instruct)',
                    'qwen-3-32b' => 'Qwen 3 32B',
                    'qwen-3-coder-480b' => 'Qwen 3 Coder 480B',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                ],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'GPT models from OpenAI',
                'models' => [
                    'gpt-5' => 'GPT-5',
                    'gpt-4' => 'GPT-4',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                ],
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'description' => 'Claude models from Anthropic',
                'models' => [
                    'claude-3-opus-20240229' => 'Claude 3 Opus',
                    'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                    'claude-3-haiku-20240307' => 'Claude 3 Haiku',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                ],
            ],
        ];
    }
}
