<template>
    <Head title="Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Heading class="mb-1">Settings</Heading>
                    <p class="text-sm text-gray-600">Configure system settings and AI providers</p>
                </div>
            </div>

            <!-- AI Provider Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Bot class="h-5 w-5 text-blue-600" />
                        AI Provider Configuration
                    </CardTitle>
                    <CardDescription>
                        Select and configure your preferred AI provider for task generation and breakdown
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateAISettings">
                        <div class="space-y-6">
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

                            <!-- Provider-specific Configuration -->
                            <div v-if="form.provider" class="space-y-4 p-4 border rounded-lg bg-gray-50">
                                <h3 class="font-medium text-gray-900">
                                    {{ availableProviders[form.provider].name }} Configuration
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-for="(fieldLabel, fieldKey) in availableProviders[form.provider].fields" :key="fieldKey" class="space-y-2">
                                        <Label :for="`${form.provider}_${fieldKey}`">{{ fieldLabel }}</Label>
                                        <Input
                                            :id="`${form.provider}_${fieldKey}`"
                                            v-model="form[`${form.provider}_${fieldKey}`]"
                                            :type="fieldKey === 'api_key' ? 'password' : 'text'"
                                            :placeholder="getFieldPlaceholder(form.provider, fieldKey)"
                                            class="w-full"
                                        />
                                        <p v-if="fieldKey === 'api_key'" class="text-xs text-gray-500">
                                            Your API key will be encrypted and stored securely
                                        </p>
                                    </div>
                                </div>

                                <!-- Test Connection Button -->
                                <div class="pt-4 border-t">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="testConnection"
                                        :disabled="isTestingConnection || !form[`${form.provider}_api_key`]"
                                        class="flex items-center gap-2"
                                    >
                                        <Loader v-if="isTestingConnection" class="h-4 w-4 animate-spin" />
                                        <Zap v-else class="h-4 w-4" />
                                        {{ isTestingConnection ? 'Testing...' : 'Test Connection' }}
                                    </Button>
                                    <p v-if="connectionTestResult" class="mt-2 text-sm" :class="connectionTestResult.success ? 'text-green-600' : 'text-red-600'">
                                        {{ connectionTestResult.message }}
                                    </p>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="flex justify-end pt-4 border-t">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex items-center gap-2"
                                >
                                    <Save class="h-4 w-4" />
                                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                                </Button>
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
import { Bot, Save, Zap, Loader } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface AISettings {
    provider: string;
    cerebrus_api_key: string;
    cerebrus_base_url: string;
    cerebrus_model: string;
    openai_api_key: string;
    openai_base_url: string;
    openai_model: string;
    anthropic_api_key: string;
    anthropic_base_url: string;
    anthropic_model: string;
}

interface ProviderInfo {
    name: string;
    description: string;
    fields: Record<string, string>;
}

interface Props {
    aiSettings: AISettings;
    availableProviders: Record<string, ProviderInfo>;
}

const props = defineProps<Props>();

const form = useForm({
    provider: props.aiSettings.provider,
    cerebrus_api_key: props.aiSettings.cerebrus_api_key,
    cerebrus_base_url: props.aiSettings.cerebrus_base_url,
    cerebrus_model: props.aiSettings.cerebrus_model,
    openai_api_key: props.aiSettings.openai_api_key,
    openai_base_url: props.aiSettings.openai_base_url,
    openai_model: props.aiSettings.openai_model,
    anthropic_api_key: props.aiSettings.anthropic_api_key,
    anthropic_base_url: props.aiSettings.anthropic_base_url,
    anthropic_model: props.aiSettings.anthropic_model,
});

const isTestingConnection = ref(false);
const connectionTestResult = ref<{ success: boolean; message: string } | null>(null);

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
    } catch {
        connectionTestResult.value = {
            success: false,
            message: 'Failed to test connection. Please check your settings.',
        };
    } finally {
        isTestingConnection.value = false;
    }
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
