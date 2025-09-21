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
        $user = auth()->user();

        // Check permissions for different sections
        $canAccessProviderConfig = $user->isSuperAdmin();
        $canAccessDefaultConfig = $user->isSuperAdmin() ||
            $user->isAdmin() ||
            ($user->organization && $user->organization->is_default);
        $canAccessOrganizationConfig = $user->isAdmin() && $user->organization && !$user->organization->is_default;
        // Get current default AI configuration for new projects (provider and model only)
        $defaultAISettings = [
            'provider' => Setting::get('ai.default.provider', 'cerebrus'),
            'model' => Setting::get('ai.default.model'),
        ];

        // Get organization-specific AI settings for admins
        $organizationAISettings = null;
        if ($canAccessOrganizationConfig && $user->organization) {
            $orgId = $user->organization->id;
            $organizationAISettings = [
                'provider' => Setting::get("ai.organization.{$orgId}.provider", 'cerebrus'),
                'model' => Setting::get("ai.organization.{$orgId}.model"),
            ];
        }

        // Get provider-specific configurations (API keys and base URLs only)
        $providerConfigs = [
            'cerebrus' => [
                'api_key' => Setting::get('ai.cerebrus.api_key', ''),
                'base_url' => Setting::get('ai.cerebrus.base_url', 'https://api.cerebras.ai/v1'),
            ],
            'openai' => [
                'api_key' => Setting::get('ai.openai.api_key', ''),
                'base_url' => Setting::get('ai.openai.base_url', 'https://api.openai.com/v1'),
            ],
            'anthropic' => [
                'api_key' => Setting::get('ai.anthropic.api_key', ''),
                'base_url' => Setting::get('ai.anthropic.base_url', 'https://api.anthropic.com/v1'),
            ],
        ];

        $availableProviders = \App\Services\AIConfigurationService::getAvailableProviders();

        return Inertia::render('settings/System', [
            'defaultAISettings' => $defaultAISettings,
            'organizationAISettings' => $organizationAISettings,
            'providerConfigs' => $providerConfigs,
            'availableProviders' => $availableProviders,
            'permissions' => [
                'canAccessProviderConfig' => $canAccessProviderConfig,
                'canAccessDefaultConfig' => $canAccessDefaultConfig,
                'canAccessOrganizationConfig' => $canAccessOrganizationConfig,
            ],
            'user' => [
                'is_super_admin' => $user->isSuperAdmin(),
                'is_admin' => $user->isAdmin(),
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                    'is_default' => $user->organization->is_default,
                ] : null,
            ],
        ]);
    }

    /**
     * Update AI settings.
     */
    public function updateAI(Request $request)
    {
        // Only Super Admins can update provider-specific configurations
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admins can access provider-specific configurations.');
        }
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'cerebrus_api_key' => 'nullable|string|max:255',
            'cerebrus_base_url' => 'nullable|url|max:255',
            'openai_api_key' => 'nullable|string|max:255',
            'openai_base_url' => 'nullable|url|max:255',
            'anthropic_api_key' => 'nullable|string|max:255',
            'anthropic_base_url' => 'nullable|url|max:255',
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
        }

        return redirect()->route('settings.system.index')->with('message', 'AI settings updated successfully!');
    }

    /**
     * Update default AI settings for new projects.
     */
    public function updateDefaultAI(Request $request)
    {
        $user = auth()->user();

        // Check if user can access default AI configuration
        $canAccess = $user->isSuperAdmin() ||
            $user->isAdmin() ||
            ($user->organization && $user->organization->is_default);

        if (!$canAccess) {
            abort(403, 'You do not have permission to modify default AI settings.');
        }
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'model' => 'required|string|max:100',
        ]);

        // Update default AI settings (provider and model only)
        Setting::set('ai.default.provider', $validated['provider'], 'string', 'Default AI provider for new projects');
        Setting::set('ai.default.model', $validated['model'], 'string', 'Default AI model for new projects');

        return redirect()->route('settings.system.index')->with('message', 'Default AI settings updated successfully!');
    }

    /**
     * Update organization-specific AI settings.
     */
    public function updateOrganizationAI(Request $request)
    {
        $user = auth()->user();

        // Only Admins of non-default organizations can update organization AI settings
        if (!$user->isAdmin() || !$user->organization || $user->organization->is_default) {
            abort(403, 'You do not have permission to modify organization AI settings.');
        }

        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'model' => 'required|string|max:100',
        ]);

        $orgId = $user->organization->id;

        // Update organization-specific AI settings
        Setting::set("ai.organization.{$orgId}.provider", $validated['provider'], 'string', "Default AI provider for organization {$user->organization->name}");
        Setting::set("ai.organization.{$orgId}.model", $validated['model'], 'string', "Default AI model for organization {$user->organization->name}");

        return redirect()->route('settings.system.index')->with('message', 'Organization AI settings updated successfully!');
    }

    /**
     * Test AI provider connection.
     */
    public function testAI(Request $request)
    {
        $user = auth()->user();

        // Check if user has permission to test AI configurations
        $canTestProvider = $user->isSuperAdmin();
        $canTestDefault = $user->isSuperAdmin() ||
            $user->isAdmin() ||
            ($user->organization && $user->organization->is_default);

        if (!$canTestProvider && !$canTestDefault) {
            abort(403, 'You do not have permission to test AI configurations.');
        }
        $validated = $request->validate([
            'provider' => 'required|in:cerebrus,openai,anthropic',
            'api_key' => 'required|string',
            'base_url' => 'required|url',
        ]);

        try {
            // Temporarily override config for testing
            config([
                "ai.providers.{$validated['provider']}.api_key" => $validated['api_key'],
                "ai.providers.{$validated['provider']}.base_url" => $validated['base_url'],
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
