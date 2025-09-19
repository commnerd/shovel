<template>
    <Head title="System Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Heading class="flex items-center gap-2">
                        <Settings class="h-6 w-6 text-blue-600" />
                        System Settings
                    </Heading>
                    <p class="text-sm text-gray-600 mt-1">Configure default AI settings for new projects and manage provider configurations</p>
                </div>
            </div>

            <!-- Default AI Configuration for New Projects -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-purple-600" />
                        Default AI Configuration
                    </CardTitle>
                    <CardDescription>
                        Set the default AI provider and model for new projects. Each project will inherit these settings and can be customized individually to control costs.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateDefaultAISettings" class="space-y-6">
                        <!-- Default Provider Selection -->
                        <div class="space-y-3">
                            <Label class="text-sm font-medium">Default AI Provider for New Projects</Label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label
                                    v-for="(provider, key) in availableProviders"
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
                        <div v-if="defaultForm.provider && availableProviders[defaultForm.provider]?.models" class="space-y-2">
                            <Label :for="`default-model-${defaultForm.provider}`">Default Model</Label>
                            <select
                                :id="`default-model-${defaultForm.provider}`"
                                v-model="defaultForm.model"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                            >
                                <option value="">Select a model...</option>
                                <option
                                    v-for="(modelName, modelKey) in availableProviders[defaultForm.provider].models"
                                    :key="modelKey"
                                    :value="modelKey"
                                >
                                    {{ modelName }}
                                </option>
                            </select>
                        </div>

                        <!-- Default Configuration Fields -->
                        <div v-if="defaultForm.provider" class="space-y-4">
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <Info class="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                    <div class="text-sm text-blue-800">
                                        <p class="font-medium">Project Inheritance</p>
                                        <p class="mt-1">New projects will automatically use these settings. Leave fields empty to use provider-specific configurations below.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label :for="`default-api-key`">API Key (Optional)</Label>
                                <Input
                                    :id="`default-api-key`"
                                    type="password"
                                    v-model="defaultForm.api_key"
                                    placeholder="Leave empty to use provider-specific API key"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label :for="`default-base-url`">Base URL (Optional)</Label>
                                <Input
                                    :id="`default-base-url`"
                                    type="url"
                                    v-model="defaultForm.base_url"
                                    :placeholder="getDefaultBaseUrl(defaultForm.provider)"
                                />
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
                            <Button
                                type="button"
                                variant="outline"
                                @click="testDefaultConnection"
                                :disabled="isTestingDefaultConnection || !defaultForm.provider || !defaultForm.model"
                                class="flex items-center gap-2"
                            >
                                <Loader v-if="isTestingDefaultConnection" class="h-4 w-4 animate-spin" />
                                <Zap v-else class="h-4 w-4" />
                                Test Default Configuration
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Provider-Specific Configuration -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Cog class="h-5 w-5 text-gray-600" />
                        Provider-Specific Configuration
                    </CardTitle>
                    <CardDescription>
                        Configure individual AI providers. These settings are used as fallbacks when projects don't have specific configurations.
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
                                    v-model="form[`${form.provider}_${field}`]"
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
import { Settings, Sparkles, Cog, Save, Zap, Loader, Info } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface DefaultAISettings {
    provider: string;
    model: string;
    api_key: string;
    base_url: string;
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

interface Props {
    defaultAISettings: DefaultAISettings;
    providerConfigs: Record<string, ProviderConfig>;
    availableProviders: Record<string, ProviderInfo>;
}

const props = defineProps<Props>();

// Default AI settings form
const defaultForm = useForm({
    provider: props.defaultAISettings?.provider || 'cerebrus',
    model: props.defaultAISettings?.model || '',
    api_key: props.defaultAISettings?.api_key || '',
    base_url: props.defaultAISettings?.base_url || '',
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
});

const isTestingConnection = ref(false);
const isTestingDefaultConnection = ref(false);
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

const testDefaultConnection = async () => {
    isTestingDefaultConnection.value = true;
    connectionTestResult.value = null;

    try {
        const response = await fetch('/settings/ai/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                provider: defaultForm.provider,
                api_key: defaultForm.api_key || props.providerConfigs[defaultForm.provider]?.api_key,
                base_url: defaultForm.base_url || props.providerConfigs[defaultForm.provider]?.base_url,
                model: defaultForm.model,
            }),
        });

        const data = await response.json();
        connectionTestResult.value = {
            success: data.success,
            message: data.message || (data.success ? 'Default configuration works!' : 'Default configuration failed'),
        };
    } catch (error) {
        connectionTestResult.value = {
            success: false,
            message: 'Failed to test default configuration.',
        };
    } finally {
        isTestingDefaultConnection.value = false;
    }
};

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
                api_key: form[`${form.provider}_api_key`],
                base_url: form[`${form.provider}_base_url`],
                model: form[`${form.provider}_model`],
            }),
        });

        const data = await response.json();
        connectionTestResult.value = {
            success: data.success,
            message: data.message || (data.success ? 'Connection successful!' : 'Connection failed'),
        };
    } catch (error) {
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
