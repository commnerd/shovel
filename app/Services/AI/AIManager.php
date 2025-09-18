<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\CerebrusProvider;
use Illuminate\Support\Manager;

class AIManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'cerebrus');
    }

    /**
     * Create the Cerebrus AI provider.
     */
    protected function createCerebrusDriver(): AIProviderInterface
    {
        $config = $this->config->get('ai.providers.cerebrus');

        return new CerebrusProvider($config);
    }

    /**
     * Create the OpenAI provider.
     */
    protected function createOpenaiDriver(): AIProviderInterface
    {
        $config = $this->config->get('ai.providers.openai');

        // This would be implemented when adding OpenAI support
        throw new \InvalidArgumentException('OpenAI provider not yet implemented');
    }

    /**
     * Create the Anthropic provider.
     */
    protected function createAnthropicDriver(): AIProviderInterface
    {
        $config = $this->config->get('ai.providers.anthropic');

        // This would be implemented when adding Anthropic support
        throw new \InvalidArgumentException('Anthropic provider not yet implemented');
    }

    /**
     * Create the Gemini provider.
     */
    protected function createGeminiDriver(): AIProviderInterface
    {
        $config = $this->config->get('ai.providers.gemini');

        // This would be implemented when adding Gemini support
        throw new \InvalidArgumentException('Gemini provider not yet implemented');
    }

    /**
     * Get a provider by name.
     */
    public function provider(string $name = null): AIProviderInterface
    {
        return $this->driver($name);
    }

    /**
     * Generate tasks using the specified or default provider.
     */
    public function generateTasks(string $projectDescription, array $options = []): array
    {
        $provider = $options['provider'] ?? null;

        return $this->provider($provider)->generateTasks($projectDescription, $options);
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
        $providers = $this->config->get('ai.providers', []);

        foreach ($providers as $name => $config) {
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
        $configs = $this->config->get('ai.providers', []);

        foreach ($configs as $name => $config) {
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
    public function testProvider(string $name = null): array
    {
        try {
            $provider = $this->provider($name);

            if (!$provider->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Provider is not properly configured',
                ];
            }

            // Test with a simple message
            $response = $provider->chat([
                ['role' => 'user', 'content' => 'Say "Hello, I am working!" and nothing else.']
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
                'message' => 'Provider test failed: ' . $e->getMessage(),
            ];
        }
    }
}
