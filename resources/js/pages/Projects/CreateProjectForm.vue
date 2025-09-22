<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    ai_provider: string;
    ai_model: string;
}

interface Props {
    userGroups: Group[];
    defaultGroupId?: number;
    defaultAISettings: DefaultAISettings;
    availableProviders: Record<string, ProviderInfo>;
    formData?: FormData;
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

const form = useForm({
    title: props.formData?.title || '',
    description: props.formData?.description || '',
    due_date: props.formData?.due_date || '',
    group_id: props.formData?.group_id || props.defaultGroupId || null,
    ai_provider: props.formData?.ai_provider || props.defaultAISettings.provider || 'cerebrus',
    ai_model: props.formData?.ai_model || props.defaultAISettings.model || '',
});

const isGeneratingTasks = ref(false);

const generateTasks = () => {
    if (!form.description.trim() || isGeneratingTasks.value) return;

    isGeneratingTasks.value = true;

    // Redirect to task generation page with project data
    router.visit('/dashboard/projects/create/tasks', {
        method: 'post',
        data: {
            title: form.title,
            description: form.description,
            due_date: form.due_date,
            group_id: form.group_id,
            ai_provider: form.ai_provider,
            ai_model: form.ai_model,
        },
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

                <div class="space-y-2">
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

                <!-- AI Configuration -->
                <div class="space-y-4 p-4 border rounded-lg bg-gray-50">
                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                        <Bot class="h-4 w-4 text-blue-600" />
                        AI Configuration
                    </h3>

                    <!-- AI Provider Selection -->
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

                <div class="text-xs text-gray-500 pt-2">
                    Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 rounded">⌘ + Enter</kbd> to generate tasks
                </div>
            </CardContent>

            <CardFooter>
                <Button
                    type="submit"
                    class="w-full flex items-center gap-2"
                    :disabled="form.processing || isGeneratingTasks || !form.description.trim()"
                    data-testid="generate-tasks-button"
                >
                    <Wand2 v-if="!isGeneratingTasks" class="h-4 w-4 sm:h-4 sm:w-4" />
                    <div v-else class="h-4 w-4 sm:h-4 sm:w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                    {{ isGeneratingTasks ? 'Generating Tasks...' : 'Generate Tasks with AI' }}
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
