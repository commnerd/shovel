<template>
    <Head title="System Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Heading title="System Settings" class="flex items-center gap-2">
                        <Settings class="h-6 w-6 text-blue-600" />
                    </Heading>
                    <p class="text-sm text-gray-600 mt-1">
                        <span v-if="props.user.is_super_admin">Configure system-wide AI providers and default settings for all organizations</span>
                        <span v-else-if="props.user.is_admin">Configure AI settings for your organization</span>
                        <span v-else>Configure default AI settings for new projects</span>
                    </p>
                </div>
            </div>

            <!-- Default AI Configuration for New Projects -->
            <Card v-if="props.permissions.canAccessDefaultConfig">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-purple-600" />
                        <span v-if="props.user.is_super_admin">System-Wide Default AI Configuration</span>
                        <span v-else>Default AI Configuration</span>
                    </CardTitle>
                    <CardDescription>
                        <span v-if="props.user.is_super_admin">
                            Set the system-wide default AI provider and model for all new projects across all organizations.
                            Organizations can override these defaults with their own settings.
                        </span>
                        <span v-else>
                            Set the default AI provider and model for new projects. Each project will inherit these settings and can be customized individually to control costs.
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- No Configured Providers Warning -->
                    <div v-if="Object.keys(configuredProviders).length === 0" class="text-center py-8 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <Zap class="h-12 w-12 text-yellow-500 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-yellow-800 mb-2">No AI Providers Configured</h3>
                        <p class="text-sm text-yellow-700 mb-4">
                            Configure at least one AI provider below to set system defaults.
                        </p>
                    </div>

                    <form v-else @submit.prevent="updateDefaultAISettings" class="space-y-6">
                        <!-- Default Provider Selection -->
                        <div class="space-y-3">
                            <Label class="text-sm font-medium">Default AI Provider for New Projects</Label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label
                                    v-for="(provider, key) in configuredProviders"
                                    :key="key"
                                    class="flex items-center space-x-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                                    :class="{ 'border-purple-500 bg-purple-50': defaultForm.provider === key }"
                                >
                                    <input
                                        type="radio"
                                        :id="`default-provider-${key}`"
                                        :value="key"
                                        v-model="defaultForm.provider"
                                        class="text-purple-600"
                                    />
                                    <div>
                                        <div class="font-medium">{{ provider.name }}</div>
                                        <div class="text-xs text-gray-500">{{ provider.description }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Default Model Selection -->
                        <div v-if="defaultForm.provider && configuredProviders[defaultForm.provider]?.models" class="space-y-2">
                            <Label :for="`default-model-${defaultForm.provider}`">Default Model</Label>
                            <select
                                :id="`default-model-${defaultForm.provider}`"
                                v-model="defaultForm.model"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                            >
                                <option value="">Select a model...</option>
                                <option
                                    v-for="(modelName, modelKey) in configuredProviders[defaultForm.provider].models"
                                    :key="modelKey"
                                    :value="modelKey"
                                >
                                    {{ modelName }}
                                </option>
                            </select>
                        </div>

                        <!-- Default Configuration Info -->
                        <div v-if="defaultForm.provider" class="space-y-4">
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <Info class="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                    <div class="text-sm text-blue-800">
                                        <p class="font-medium">Project Inheritance</p>
                                        <p class="mt-1">
                                            New projects will automatically use this provider and model.
                                            API credentials are configured by Super Admins in the provider-specific section.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-4 border-t">
                            <Button
                                type="submit"
                                :disabled="defaultForm.processing || !defaultForm.provider || !defaultForm.model"
                                class="flex items-center gap-2"
                            >
                                <Save class="h-4 w-4" />
                                {{ defaultForm.processing ? 'Saving...' : 'Save Default Settings' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Organization AI Configuration (for Admins) -->
            <Card v-if="props.permissions.canAccessOrganizationConfig">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-green-600" />
                        Organization AI Configuration
                    </CardTitle>
                    <CardDescription>
                        Set the default AI provider and model for all projects in {{ props.user.organization?.name }}.
                        This will override the system defaults for your organization.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- No Configured Providers Warning for Organization -->
                    <div v-if="Object.keys(configuredProviders).length === 0" class="text-center py-8 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <Zap class="h-12 w-12 text-yellow-500 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-yellow-800 mb-2">No AI Providers Configured</h3>
                        <p class="text-sm text-yellow-700 mb-4">
                            Configure at least one AI provider below to set organization defaults.
                        </p>
                    </div>

                    <form v-else @submit.prevent="updateOrganizationAISettings" class="space-y-6">
                        <!-- Organization Provider Selection -->
                        <div class="space-y-3">
                            <Label for="org-provider">AI Provider</Label>
                            <Select v-model="organizationForm.provider" :disabled="organizationForm.processing">
                                <SelectTrigger id="org-provider">
                                    <SelectValue placeholder="Select AI provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(provider, key) in configuredProviders" :key="key" :value="key">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                            <div>
                                                <div class="font-medium">{{ provider.name }}</div>
                                                <div class="text-xs text-gray-500">{{ provider.description }}</div>
                                            </div>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="organizationForm.errors.provider" />
                        </div>

                        <!-- Organization Model Selection -->
                        <div v-if="organizationForm.provider" class="space-y-3">
                            <Label for="org-model">AI Model</Label>
                            <Select v-model="organizationForm.model" :disabled="organizationForm.processing">
                                <SelectTrigger id="org-model">
                                    <SelectValue placeholder="Select AI model" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(modelName, modelKey) in configuredProviders[organizationForm.provider]?.models || {}"
                                        :key="modelKey"
                                        :value="modelKey"
                                    >
                                        {{ modelName }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="organizationForm.errors.model" />
                            <p class="text-xs text-gray-500">
                                This model will be used for all AI operations in your organization unless overridden at the project level.
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-3">
                            <Button type="submit" :disabled="organizationForm.processing">
                                <Loader2 v-if="organizationForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                {{ organizationForm.processing ? 'Updating...' : 'Update Organization Settings' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Provider-Specific Configuration -->
            <Card v-if="props.permissions.canAccessProviderConfig">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Cog class="h-5 w-5 text-gray-600" />
                        System-Wide AI Provider Configuration
                    </CardTitle>
                    <CardDescription>
                        Configure API keys and endpoints for AI providers across the entire platform.
                        These credentials will be used by all organizations and projects that don't have their own provider configurations.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateAISettings" class="space-y-6">
                        <!-- Provider Selection -->
                        <div class="space-y-3">
                            <Label class="text-base font-medium">Select AI Provider</Label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div
                                    v-for="(provider, key) in availableProviders"
                                    :key="key"
                                    class="relative"
                                >
                                    <input
                                        :id="`provider-${key}`"
                                        v-model="form.provider"
                                        :value="key"
                                        type="radio"
                                        class="peer sr-only"
                                    />
                                    <label
                                        :for="`provider-${key}`"
                                        class="flex flex-col p-4 border rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-colors"
                                    >
                                        <div class="font-medium text-gray-900">{{ provider.name }}</div>
                                        <div class="text-sm text-gray-500 mt-1">{{ provider.description }}</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Provider Configuration -->
                        <div v-if="form.provider" class="space-y-4">
                            <div v-for="(label, field) in availableProviders[form.provider].fields" :key="field" class="space-y-2">
                                <Label :for="`${form.provider}-${field}`">{{ label }}</Label>
                                <Input
                                    :id="`${form.provider}-${field}`"
                                    :type="field.includes('api_key') ? 'password' : 'text'"
                                    v-model="(form as any)[`${form.provider}_${field}`]"
                                    :placeholder="getFieldPlaceholder(form.provider, field)"
                                />
                            </div>
                        </div>

                        <!-- Test Connection & Save -->
                        <div class="flex gap-3 pt-4 border-t">
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.provider"
                                class="flex items-center gap-2"
                            >
                                <Save class="h-4 w-4" />
                                {{ form.processing ? 'Saving...' : 'Save Provider Settings' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="testConnection"
                                :disabled="isTestingConnection || !form.provider"
                                class="flex items-center gap-2"
                            >
                                <Loader v-if="isTestingConnection" class="h-4 w-4 animate-spin" />
                                <Zap v-else class="h-4 w-4" />
                                Test Connection
                            </Button>
                        </div>

                        <!-- Connection Test Result -->
                        <div v-if="connectionTestResult" class="mt-4">
                            <div
                                class="p-3 rounded-lg border"
                                :class="connectionTestResult.success
                                    ? 'bg-green-50 border-green-200 text-green-800'
                                    : 'bg-red-50 border-red-200 text-red-800'"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="connectionTestResult.success ? 'bg-green-500' : 'bg-red-500'"></div>
                                    <span class="text-sm font-medium">{{ connectionTestResult.message }}</span>
                                </div>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- No Access Message -->
            <Card v-if="!props.permissions.canAccessDefaultConfig && !props.permissions.canAccessProviderConfig" class="border-amber-200 bg-amber-50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-amber-800">
                        <Info class="h-5 w-5" />
                        Access Restricted
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-amber-700">
                        <p class="mb-2">You don't have permission to access AI configuration settings.</p>
                        <div class="text-sm space-y-1">
                            <p><strong>Default AI Configuration:</strong> Available to Super Admins, Organization Admins, and users in the 'None' organization.</p>
                            <p><strong>Provider-Specific Configuration:</strong> Available to Super Admins only.</p>
                        </div>
                        <p class="mt-3 text-sm">Contact your administrator if you need access to these settings.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Settings, Sparkles, Cog, Save, Zap, Loader, Info, Building2, Loader2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface DefaultAISettings {
    provider: string;
    model: string;
}

interface ProviderConfig {
    api_key: string;
    base_url: string;
    model: string;
}

interface ProviderInfo {
    name: string;
    description: string;
    models: Record<string, string>;
    fields: Record<string, string>;
}

interface OrganizationAISettings {
    provider: string;
    model: string;
}

interface User {
    is_super_admin: boolean;
    is_admin: boolean;
    organization: {
        id: number;
        name: string;
        is_default: boolean;
    } | null;
}

interface Props {
    defaultAISettings: DefaultAISettings;
    organizationAISettings: OrganizationAISettings | null;
    providerConfigs: Record<string, ProviderConfig>;
    availableProviders: Record<string, ProviderInfo>;
    configuredProviders: Record<string, ProviderInfo>;
    permissions: {
        canAccessProviderConfig: boolean;
        canAccessDefaultConfig: boolean;
        canAccessOrganizationConfig: boolean;
    };
    user: User;
}

const props = defineProps<Props>();

// Default AI settings form (provider and model only)
const defaultForm = useForm({
    provider: props.defaultAISettings?.provider || 'cerebrus',
    model: props.defaultAISettings?.model || '',
});

// Organization AI settings form
const organizationForm = useForm({
    provider: props.organizationAISettings?.provider || 'cerebrus',
    model: props.organizationAISettings?.model || '',
});

// Provider-specific settings form
const form = useForm({
    provider: props.defaultAISettings?.provider || 'cerebrus',
    cerebrus_api_key: props.providerConfigs?.cerebrus?.api_key || '',
    cerebrus_base_url: props.providerConfigs?.cerebrus?.base_url || '',
    cerebrus_model: props.providerConfigs?.cerebrus?.model || '',
    openai_api_key: props.providerConfigs?.openai?.api_key || '',
    openai_base_url: props.providerConfigs?.openai?.base_url || '',
    openai_model: props.providerConfigs?.openai?.model || '',
    anthropic_api_key: props.providerConfigs?.anthropic?.api_key || '',
    anthropic_base_url: props.providerConfigs?.anthropic?.base_url || '',
    anthropic_model: props.providerConfigs?.anthropic?.model || '',
} as Record<string, any>);

const isTestingConnection = ref(false);
const connectionTestResult = ref<{ success: boolean; message: string } | null>(null);

const getDefaultBaseUrl = (provider: string): string => {
    const defaults: Record<string, string> = {
        cerebrus: 'https://api.cerebras.ai/v1',
        openai: 'https://api.openai.com/v1',
        anthropic: 'https://api.anthropic.com/v1',
    };
    return defaults[provider] || '';
};

const getFieldPlaceholder = (provider: string, field: string): string => {
    const placeholders: Record<string, Record<string, string>> = {
        cerebrus: {
            api_key: 'Enter your Cerebras API key',
            base_url: 'https://api.cerebras.ai/v1',
            model: 'llama3.1-8b',
        },
        openai: {
            api_key: 'Enter your OpenAI API key',
            base_url: 'https://api.openai.com/v1',
            model: 'gpt-4',
        },
        anthropic: {
            api_key: 'Enter your Anthropic API key',
            base_url: 'https://api.anthropic.com/v1',
            model: 'claude-3-sonnet-20240229',
        },
    };

    return placeholders[provider]?.[field] || '';
};

// Test default connection removed - credentials are managed by Super Admins

const testConnection = async () => {
    isTestingConnection.value = true;
    connectionTestResult.value = null;

    try {
        const response = await fetch('/settings/ai/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                provider: form.provider,
                api_key: (form as any)[`${form.provider}_api_key`],
                base_url: (form as any)[`${form.provider}_base_url`],
                model: (form as any)[`${form.provider}_model`],
            }),
        });

        const data = await response.json();
        connectionTestResult.value = {
            success: data.success,
            message: data.message || (data.success ? 'Connection successful!' : 'Connection failed'),
        };
    } catch {
        connectionTestResult.value = {
            success: false,
            message: 'Failed to test connection. Please check your settings.',
        };
    } finally {
        isTestingConnection.value = false;
    }
};

const updateDefaultAISettings = () => {
    defaultForm.post('/settings/ai/default', {
        onSuccess: () => {
            connectionTestResult.value = null;
        },
    });
};

const updateOrganizationAISettings = () => {
    organizationForm.post('/settings/ai/organization', {
        onSuccess: () => {
            connectionTestResult.value = null;
        },
    });
};

const updateAISettings = () => {
    form.post('/settings/ai', {
        onSuccess: () => {
            connectionTestResult.value = null;
        },
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'System Settings',
        href: '#',
    },
];
</script>
