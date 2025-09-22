<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\{CerebrusProvider, OpenAIProvider};
use Illuminate\Support\Manager;

class AIManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return \App\Models\Setting::get('ai.default.provider', 'cerebrus');
    }

    /**
     * Create the Cerebrus AI provider.
     */
    protected function createCerebrusDriver(): AIProviderInterface
    {
        // Get all configuration from database settings
        $config = [
            'driver' => 'cerebrus',
            'api_key' => \App\Models\Setting::get('ai.cerebrus.api_key'),
            'base_url' => \App\Models\Setting::get('ai.cerebrus.base_url', 'https://api.cerebras.ai/v1'),
            'model' => \App\Models\Setting::get('ai.cerebrus.model', 'llama-4-scout-17b-16e-instruct'),
            'timeout' => \App\Models\Setting::get('ai.cerebrus.timeout', 30),
            'max_tokens' => \App\Models\Setting::get('ai.cerebrus.max_tokens', 4000),
            'temperature' => \App\Models\Setting::get('ai.cerebrus.temperature', 0.7),
        ];

        return new CerebrusProvider($config);
    }

    /**
     * Create the OpenAI provider.
     */
    protected function createOpenaiDriver(): AIProviderInterface
    {
        // Get all configuration from database settings
        $config = [
            'driver' => 'openai',
            'api_key' => \App\Models\Setting::get('ai.openai.api_key'),
            'organization' => \App\Models\Setting::get('ai.openai.organization'),
            'base_url' => \App\Models\Setting::get('ai.openai.base_url', 'https://api.openai.com/v1'),
            'model' => \App\Models\Setting::get('ai.openai.model', 'gpt-4'),
            'timeout' => \App\Models\Setting::get('ai.openai.timeout', 30),
            'max_tokens' => \App\Models\Setting::get('ai.openai.max_tokens', 4000),
            'temperature' => \App\Models\Setting::get('ai.openai.temperature', 0.7),
        ];

        return new OpenAIProvider($config);
    }

    /**
     * Create the Anthropic provider.
     */
    protected function createAnthropicDriver(): AIProviderInterface
    {
        // Get all configuration from database settings
        $config = [
            'driver' => 'anthropic',
            'api_key' => \App\Models\Setting::get('ai.anthropic.api_key'),
            'base_url' => \App\Models\Setting::get('ai.anthropic.base_url', 'https://api.anthropic.com/v1'),
            'model' => \App\Models\Setting::get('ai.anthropic.model', 'claude-3-sonnet-20240229'),
            'timeout' => \App\Models\Setting::get('ai.anthropic.timeout', 30),
            'max_tokens' => \App\Models\Setting::get('ai.anthropic.max_tokens', 4000),
            'temperature' => \App\Models\Setting::get('ai.anthropic.temperature', 0.7),
        ];

        // This would be implemented when adding Anthropic support
        throw new \InvalidArgumentException('Anthropic provider not yet implemented');
    }

    /**
     * Create the Gemini provider.
     */
    protected function createGeminiDriver(): AIProviderInterface
    {
        // Get all configuration from database settings
        $config = [
            'driver' => 'gemini',
            'api_key' => \App\Models\Setting::get('ai.gemini.api_key'),
            'base_url' => \App\Models\Setting::get('ai.gemini.base_url', 'https://generativelanguage.googleapis.com'),
            'model' => \App\Models\Setting::get('ai.gemini.model', 'gemini-pro'),
            'timeout' => \App\Models\Setting::get('ai.gemini.timeout', 30),
            'max_tokens' => \App\Models\Setting::get('ai.gemini.max_tokens', 4000),
            'temperature' => \App\Models\Setting::get('ai.gemini.temperature', 0.7),
        ];

        // This would be implemented when adding Gemini support
        throw new \InvalidArgumentException('Gemini provider not yet implemented');
    }

    /**
     * Get a provider by name.
     */
    public function provider(?string $name = null): AIProviderInterface
    {
        return $this->driver($name);
    }

    /**
     * Generate tasks using the specified or default provider.
     */
    public function generateTasks(string $projectDescription, array $schema = [], array $options = []): \App\Services\AI\Contracts\AITaskResponse
    {
        $provider = $options['provider'] ?? null;

        return $this->provider($provider)->generateTasks($projectDescription, $schema, $options);
    }

    /**
     * Break down a task into subtasks using the specified or default provider.
     */
    public function breakdownTask(string $taskTitle, string $taskDescription, array $context = [], array $options = []): \App\Services\AI\Contracts\AITaskResponse
    {
        $provider = $options['provider'] ?? null;

        return $this->provider($provider)->breakdownTask($taskTitle, $taskDescription, $context, $options);
    }

    /**
     * Analyze a project using the specified or default provider.
     */
    public function analyzeProject(string $projectDescription, array $existingTasks = [], array $options = []): string
    {
        $provider = $options['provider'] ?? null;

        return $this->provider($provider)->analyzeProject($projectDescription, $existingTasks, $options);
    }

    /**
     * Get task suggestions using the specified or default provider.
     */
    public function suggestTaskImprovements(array $tasks, array $options = []): array
    {
        $provider = $options['provider'] ?? null;

        return $this->provider($provider)->suggestTaskImprovements($tasks, $options);
    }

    /**
     * Check if any provider is configured.
     */
    public function hasConfiguredProvider(): bool
    {
        $providerNames = ['cerebrus', 'openai', 'anthropic', 'gemini'];

        foreach ($providerNames as $name) {
            try {
                $provider = $this->provider($name);
                if ($provider->isConfigured()) {
                    return true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return false;
    }

    /**
     * Get all available providers.
     */
    public function getAvailableProviders(): array
    {
        $providers = [];
        // Define available provider names since we no longer use config file
        $providerNames = ['cerebrus', 'openai', 'anthropic', 'gemini'];

        foreach ($providerNames as $name) {
            try {
                $provider = $this->provider($name);
                $providers[$name] = [
                    'name' => $provider->getName(),
                    'configured' => $provider->isConfigured(),
                    'config' => $provider->getConfig(),
                ];
            } catch (\Exception $e) {
                $providers[$name] = [
                    'name' => $name,
                    'configured' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $providers;
    }

    /**
     * Test a provider connection.
     */
    public function testProvider(?string $name = null): array
    {
        try {
            $provider = $this->provider($name);

            if (! $provider->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Provider is not properly configured',
                ];
            }

            // Test with a simple message
            $response = $provider->chat([
                ['role' => 'user', 'content' => 'Say "Hello, I am working!" and nothing else.'],
            ]);

            return [
                'success' => true,
                'message' => 'Provider is working correctly',
                'response' => $response->getContent(),
                'tokens_used' => $response->getTokensUsed(),
                'response_time' => $response->getResponseTime(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Provider test failed: '.$e->getMessage(),
            ];
        }
    }
}
