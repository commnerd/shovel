<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Show the settings page.
     */
    public function index()
    {
        // Get current default AI configuration for new projects
        $defaultAISettings = [
            'provider' => Setting::get('ai.default.provider', 'cerebrus'),
            'model' => Setting::get('ai.default.model'),
            'api_key' => Setting::get('ai.default.api_key', ''),
            'base_url' => Setting::get('ai.default.base_url'),
        ];

        // Get provider-specific configurations
        $providerConfigs = [
            'cerebrus' => [
                'api_key' => Setting::get('ai.cerebrus.api_key', ''),
                'base_url' => Setting::get('ai.cerebrus.base_url', 'https://api.cerebras.ai/v1'),
                'model' => Setting::get('ai.cerebrus.model', 'llama3.1-8b'),
            ],
            'openai' => [
                'api_key' => Setting::get('ai.openai.api_key', ''),
                'base_url' => Setting::get('ai.openai.base_url', 'https://api.openai.com/v1'),
                'model' => Setting::get('ai.openai.model', 'gpt-4'),
            ],
            'anthropic' => [
                'api_key' => Setting::get('ai.anthropic.api_key', ''),
                'base_url' => Setting::get('ai.anthropic.base_url', 'https://api.anthropic.com/v1'),
                'model' => Setting::get('ai.anthropic.model', 'claude-3-sonnet-20240229'),
            ],
        ];

        $availableProviders = [
            'cerebrus' => [
                'name' => 'Cerebras',
                'description' => 'Fast and efficient AI models',
                'models' => [
                    'llama3.1-8b' => 'Llama 3.1 8B',
                    'llama3.1-70b' => 'Llama 3.1 70B',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                    'model' => 'Model',
                ],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'GPT models from OpenAI',
                'models' => [
                    'gpt-4' => 'GPT-4',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                    'model' => 'Model',
                ],
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'description' => 'Claude models from Anthropic',
                'models' => [
                    'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                    'claude-3-opus-20240229' => 'Claude 3 Opus',
                    'claude-3-haiku-20240307' => 'Claude 3 Haiku',
                ],
                'fields' => [
                    'api_key' => 'API Key',
                    'base_url' => 'Base URL',
                    'model' => 'Model',
                ],
            ],
        ];

        return Inertia::render('settings/System', [
            'defaultAISettings' => $defaultAISettings,
            'providerConfigs' => $providerConfigs,
            'availableProviders' => $availableProviders,
        ]);
    }

    /**
     * Update AI settings.
     */
    public function updateAI(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'cerebrus_api_key' => 'nullable|string|max:255',
            'cerebrus_base_url' => 'nullable|url|max:255',
            'cerebrus_model' => 'nullable|string|max:100',
            'openai_api_key' => 'nullable|string|max:255',
            'openai_base_url' => 'nullable|url|max:255',
            'openai_model' => 'nullable|string|max:100',
            'anthropic_api_key' => 'nullable|string|max:255',
            'anthropic_base_url' => 'nullable|url|max:255',
            'anthropic_model' => 'nullable|string|max:100',
        ]);

        // Update AI provider setting
        Setting::set('ai.provider', $validated['provider'], 'string', 'Active AI provider');

        // Update provider-specific settings
        foreach (['cerebrus', 'openai', 'anthropic'] as $provider) {
            if (! empty($validated["{$provider}_api_key"])) {
                Setting::set("ai.{$provider}.api_key", $validated["{$provider}_api_key"], 'string', "{$provider} API key");
            }
            if (! empty($validated["{$provider}_base_url"])) {
                Setting::set("ai.{$provider}.base_url", $validated["{$provider}_base_url"], 'string', "{$provider} base URL");
            }
            if (! empty($validated["{$provider}_model"])) {
                Setting::set("ai.{$provider}.model", $validated["{$provider}_model"], 'string', "{$provider} model");
            }
        }

        return redirect()->route('settings.system.index')->with('message', 'AI settings updated successfully!');
    }

    /**
     * Update default AI settings for new projects.
     */
    public function updateDefaultAI(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'model' => 'required|string|max:100',
            'api_key' => 'nullable|string|max:255',
            'base_url' => 'nullable|url|max:255',
        ]);

        // Update default AI settings
        Setting::set('ai.default.provider', $validated['provider'], 'string', 'Default AI provider for new projects');
        Setting::set('ai.default.model', $validated['model'], 'string', 'Default AI model for new projects');

        if (! empty($validated['api_key'])) {
            Setting::set('ai.default.api_key', $validated['api_key'], 'string', 'Default AI API key for new projects');
        }

        if (! empty($validated['base_url'])) {
            Setting::set('ai.default.base_url', $validated['base_url'], 'string', 'Default AI base URL for new projects');
        }

        return redirect()->route('settings.system.index')->with('message', 'Default AI settings updated successfully!');
    }

    /**
     * Test AI provider connection.
     */
    public function testAI(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'api_key' => 'required|string',
            'base_url' => 'required|url',
            'model' => 'required|string',
        ]);

        try {
            // Temporarily override config for testing
            config([
                "ai.providers.{$validated['provider']}.api_key" => $validated['api_key'],
                "ai.providers.{$validated['provider']}.base_url" => $validated['base_url'],
                "ai.providers.{$validated['provider']}.model" => $validated['model'],
                'ai.default_provider' => $validated['provider'],
            ]);

            // Test the connection
            $aiManager = app('ai');
            $testResult = $aiManager->testProvider($validated['provider']);

            return response()->json([
                'success' => $testResult['success'] ?? false,
                'message' => $testResult['message'] ?? 'Connection test completed',
                'details' => $testResult,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
