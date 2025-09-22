<?php

namespace App\Services;

use App\Services\AI\Facades\AI;

class AIConfigurationService
{
    /**
     * Get all possible AI providers and their models (for settings/configuration).
     * This includes both configured and unconfigured providers.
     */
    public static function getAllProviders(): array
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

    /**
     * Get only the configured and available AI providers.
     * This filters out providers that don't have API keys set.
     */
    public static function getAvailableProviders(): array
    {
        $allProviders = self::getAllProviders();
        $configuredProviders = [];

        try {
            // Get providers that are actually configured from the AI manager
            $aiProviders = AI::getAvailableProviders();

            foreach ($aiProviders as $providerKey => $providerData) {
                // Only include providers that are configured (have API keys)
                if (isset($providerData['configured']) && $providerData['configured'] === true) {
                    // Merge AI manager data with our static provider info
                    if (isset($allProviders[$providerKey])) {
                        $configuredProviders[$providerKey] = array_merge(
                            $allProviders[$providerKey],
                            [
                                'configured' => true,
                                'config' => $providerData['config'] ?? [],
                            ]
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // If AI manager fails, log the error but don't break the application
            \Log::warning('Failed to get configured AI providers: ' . $e->getMessage());
            return [];
        }

        return $configuredProviders;
    }
}
