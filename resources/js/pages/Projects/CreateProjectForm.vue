<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

interface Group {
    id: number;
    name: string;
    description?: string;
    is_default: boolean;
    organization_name: string;
}

interface DefaultAISettings {
    provider: string;
    model: string;
}

interface ProviderInfo {
    name: string;
    description: string;
    models: Record<string, string>;
}

interface FormData {
    title: string;
    description: string;
    due_date: string;
    group_id?: number;
    project_type: 'finite' | 'iterative';
    default_iteration_length_weeks?: number;
    auto_create_iterations?: boolean;
    ai_provider: string;
    ai_model: string;
}

interface Props {
    userGroups: Group[];
    defaultGroupId?: number;
    defaultAISettings: DefaultAISettings;
    availableProviders: Record<string, ProviderInfo>;
    formData?: FormData;
    userOrganizationName: string;
}

const props = defineProps<Props>();

// Define emits
defineEmits<{
    success: []
}>();

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Sparkles, Calendar, Wand2, Users, Bot } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

export interface TaskSuggestion {
    title: string;
    description: string;
    status: 'pending' | 'in_progress' | 'completed';
    sort_order: number;
}

// Get first available provider or fallback to default
const getDefaultAIProvider = () => {
    const availableProviderKeys = Object.keys(props.availableProviders);
    if (availableProviderKeys.length > 0) {
        return props.formData?.ai_provider || props.defaultAISettings.provider || availableProviderKeys[0];
    }
    return props.formData?.ai_provider || props.defaultAISettings.provider || '';
};

const form = useForm({
    title: props.formData?.title || '',
    description: props.formData?.description || '',
    due_date: props.formData?.due_date || '',
    group_id: props.formData?.group_id || props.defaultGroupId || null,
    project_type: props.formData?.project_type || 'iterative',
    default_iteration_length_weeks: props.formData?.default_iteration_length_weeks || 2,
    auto_create_iterations: props.formData?.auto_create_iterations || false,
    ai_provider: getDefaultAIProvider(),
    ai_model: props.formData?.ai_model || props.defaultAISettings.model || '',
});

// Debug logging to track form changes
watch(() => form.project_type, (newValue, oldValue) => {
    console.log('Project type changed from', oldValue, 'to', newValue);
});

// Project type selection handlers
const selectFiniteProject = () => {
    console.log('selectFiniteProject called, setting project_type to finite');
    // Force update the form data
    form.setData({
        ...form.data(),
        project_type: 'finite'
    });
    console.log('Form project_type is now:', form.data().project_type);
};

const selectIterativeProject = () => {
    console.log('selectIterativeProject called, setting project_type to iterative');
    // Force update the form data
    form.setData({
        ...form.data(),
        project_type: 'iterative'
    });
    console.log('Form project_type is now:', form.data().project_type);
};

const onProjectTypeChange = (event) => {
    console.log('Radio button changed to:', event.target.value);
    // Force update the form data
    form.setData({
        ...form.data(),
        project_type: event.target.value
    });
    console.log('Form project_type is now:', form.data().project_type);
};

const isGeneratingTasks = ref(false);

// Hide group selection for users in the 'None' organization
const shouldShowGroupSelection = computed(() => props.userOrganizationName !== 'None');

const generateTasks = () => {
    if (!form.description.trim() || isGeneratingTasks.value) return;

    // Check if any AI providers are configured
    if (Object.keys(props.availableProviders).length === 0) {
        alert('No AI providers are configured. Please configure an AI provider in Settings first.');
        return;
    }

    isGeneratingTasks.value = true;

    // Debug logging to see what's being sent
    const formData = form.data();
    console.log('Form submission data:', formData);
    console.log('Project type being sent:', formData.project_type);

    // Redirect to task generation page with project data
    router.visit('/dashboard/projects/create/tasks', {
        method: 'post',
        data: formData,
        onFinish: () => {
            isGeneratingTasks.value = false;
        },
        onError: (errors) => {
            console.error('Failed to navigate to task generation:', errors);
            isGeneratingTasks.value = false;
        }
    });
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        generateTasks();
    }
};
</script>

<template>
    <Card class="w-full" data-testid="create-project-form">
        <CardHeader class="text-center">
            <CardTitle class="flex items-center justify-center gap-2">
                <Sparkles class="h-5 w-5 text-blue-600" />
                Create New Project
            </CardTitle>
            <CardDescription>
                Describe your project and let AI create an initial task layout for you
            </CardDescription>
        </CardHeader>

        <form @submit.prevent="generateTasks">
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="title">Project Title (Optional)</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="e.g., Peanut Butter and Jelly Sandwich"
                        :disabled="form.processing || isGeneratingTasks"
                        class="w-full"
                        data-testid="project-title"
                    />
                    <InputError :message="form.errors.title" />
                    <p class="text-xs text-gray-500">
                        Leave empty to let AI generate a title for you
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="description">Project Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        placeholder="e.g., Make a peanut butter and jelly sandwich..."
                        class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                        :disabled="form.processing || isGeneratingTasks"
                        @keydown="handleKeydown"
                        data-testid="project-description"
                        required
                    ></textarea>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-2">
                    <Label for="due_date" class="flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Due Date (Optional)
                    </Label>
                    <Input
                        id="due_date"
                        v-model="form.due_date"
                        type="date"
                        :disabled="form.processing || isGeneratingTasks"
                        class="w-full"
                    />
                    <InputError :message="form.errors.due_date" />
                    <p class="text-xs text-gray-500">
                        Leave empty if no specific deadline
                    </p>
                </div>

                <div v-if="shouldShowGroupSelection" class="space-y-2">
                    <Label for="group_id" class="flex items-center gap-2">
                        <Users class="h-4 w-4" />
                        Assign to Group
                    </Label>
                    <select
                        id="group_id"
                        v-model="form.group_id"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || isGeneratingTasks"
                    >
                        <option v-for="group in userGroups" :key="group.id" :value="group.id">
                            {{ group.name }}
                            <span v-if="group.organization_name !== 'None'"> ({{ group.organization_name }})</span>
                            <span v-if="group.is_default"> - Default</span>
                        </option>
                    </select>
                    <InputError :message="form.errors.group_id" />
                    <p class="text-xs text-gray-500">
                        Choose which group this project belongs to
                    </p>
                </div>

                <!-- Project Type Selection -->
                <div class="space-y-4 p-4 border rounded-lg bg-blue-50">
                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                        <Wand2 class="h-4 w-4 text-blue-600" />
                        Project Type
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Iterative Project Option -->
                        <div
                            class="p-4 border-2 rounded-lg cursor-pointer transition-colors"
                            :class="form.project_type === 'iterative'
                                ? 'border-blue-500 bg-blue-100'
                                : 'border-gray-200 hover:border-gray-300'"
                            @click="selectIterativeProject"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <input
                                    type="radio"
                                    id="iterative"
                                    v-model="form.project_type"
                                    value="iterative"
                                    class="h-4 w-4"
                                    @change="onProjectTypeChange"
                                />
                                <Label for="iterative" class="font-medium cursor-pointer">Iterative Project</Label>
                            </div>
                            <p class="text-sm text-gray-600">
                                Agile project with sprints and continuous delivery. Ideal for ongoing development.
                            </p>
                        </div>

                        <!-- Finite Project Option -->
                        <div
                            class="p-4 border-2 rounded-lg cursor-pointer transition-colors"
                            :class="form.project_type === 'finite'
                                ? 'border-blue-500 bg-blue-100'
                                : 'border-gray-200 hover:border-gray-300'"
                            @click="selectFiniteProject"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <input
                                    type="radio"
                                    id="finite"
                                    v-model="form.project_type"
                                    value="finite"
                                    class="h-4 w-4"
                                    @change="onProjectTypeChange"
                                />
                                <Label for="finite" class="font-medium cursor-pointer">Finite Project</Label>
                            </div>
                            <p class="text-sm text-gray-600">
                                Traditional project with defined scope and timeline. Perfect for one-time deliverables.
                            </p>
                        </div>
                    </div>

                    <!-- Iterative Project Settings -->
                    <div v-if="form.project_type === 'iterative'" class="space-y-3 pt-3 border-t border-blue-200">
                        <div class="space-y-2">
                            <Label for="iteration_length" class="text-sm font-medium">
                                Default Sprint Length (weeks)
                            </Label>
                            <Input
                                id="iteration_length"
                                v-model.number="form.default_iteration_length_weeks"
                                type="number"
                                min="1"
                                max="12"
                                class="w-24"
                                :disabled="form.processing || isGeneratingTasks"
                            />
                            <p class="text-xs text-gray-500">
                                How long each sprint should last (typically 1-4 weeks)
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                id="auto_create"
                                v-model="form.auto_create_iterations"
                                class="h-4 w-4"
                                :disabled="form.processing || isGeneratingTasks"
                            />
                            <Label for="auto_create" class="text-sm font-medium cursor-pointer">
                                Automatically create new sprints
                            </Label>
                        </div>
                        <p class="text-xs text-gray-500 ml-6">
                            When enabled, new sprints will be created automatically when needed
                        </p>
                    </div>
                </div>

                <!-- AI Configuration -->
                <div class="space-y-4 p-4 border rounded-lg bg-gray-50">
                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                        <Bot class="h-4 w-4 text-blue-600" />
                        AI Configuration
                    </h3>

                    <!-- No Providers Configured -->
                    <div v-if="Object.keys(availableProviders).length === 0" class="text-center py-6">
                        <Bot class="h-12 w-12 text-gray-400 mx-auto mb-3" />
                        <h4 class="text-lg font-medium text-gray-900 mb-2">No AI Providers Configured</h4>
                        <p class="text-sm text-gray-600 mb-4">
                            To use AI features for task generation and breakdown, you need to configure at least one AI provider with an API key.
                        </p>
                        <Button
                            variant="outline"
                            @click="() => router.visit('/settings/system')"
                            class="inline-flex items-center gap-2"
                        >
                            <Bot class="h-4 w-4" />
                            Configure AI Providers
                        </Button>
                        <p class="text-xs text-gray-500 mt-3">
                            You can still create projects manually without AI assistance.
                        </p>
                    </div>

                    <!-- AI Provider Selection -->
                    <div v-else class="space-y-4">
                        <div class="space-y-2">
                            <Label for="ai_provider">AI Provider</Label>
                            <select
                                id="ai_provider"
                                v-model="form.ai_provider"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing || isGeneratingTasks"
                            >
                                <option v-for="(provider, key) in availableProviders" :key="key" :value="key">
                                    {{ provider.name }} - {{ provider.description }}
                                </option>
                            </select>
                            <InputError :message="form.errors.ai_provider" />
                        </div>

                        <!-- AI Model Selection -->
                        <div v-if="form.ai_provider && availableProviders[form.ai_provider]?.models" class="space-y-2">
                            <Label for="ai_model">AI Model</Label>
                            <select
                                id="ai_model"
                                v-model="form.ai_model"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing || isGeneratingTasks"
                            >
                                <option value="">Select a model...</option>
                                <option
                                    v-for="(modelName, modelKey) in availableProviders[form.ai_provider].models"
                                    :key="modelKey"
                                    :value="modelKey"
                                >
                                    {{ modelName }}
                                </option>
                            </select>
                            <InputError :message="form.errors.ai_model" />
                        </div>

                        <p class="text-xs text-gray-500">
                            Configure which AI provider and model to use for generating tasks for this project.
                            This will be used for task generation and breakdown features.
                        </p>
                    </div>
                </div>

                <div class="text-xs text-gray-500 pt-2">
                    Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 rounded">⌘ + Enter</kbd> to generate tasks
                </div>
            </CardContent>

            <CardFooter>
                <Button
                    type="submit"
                    class="w-full flex items-center gap-2"
                    :disabled="form.processing || isGeneratingTasks || !form.description.trim() || Object.keys(availableProviders).length === 0"
                    data-testid="generate-tasks-button"
                >
                    <Wand2 v-if="!isGeneratingTasks" class="h-4 w-4 sm:h-4 sm:w-4" />
                    <div v-else class="h-4 w-4 sm:h-4 sm:w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                    {{
                        Object.keys(availableProviders).length === 0
                            ? 'Configure AI Provider First'
                            : isGeneratingTasks
                                ? 'Generating Tasks...'
                                : 'Generate Tasks with AI'
                    }}
                </Button>
            </CardFooter>
        </form>

    </Card>
</template>

<style scoped>
kbd {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
}
</style>
