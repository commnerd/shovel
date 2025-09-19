<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Plus, Send, Sparkles, Loader } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Project {
    id: number;
    description: string;
}

interface ParentTask {
    id: number;
    title: string;
}

const props = defineProps<{
    project: Project;
    parentTasks: ParentTask[];
    parentTask?: ParentTask; // Pre-selected parent task for subtask creation
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
    },
    {
        title: props.project.title || 'Untitled Project',
        href: `/dashboard/projects/${props.project.id}/tasks`,
    },
    {
        title: props.parentTask ? `Create Subtask for "${props.parentTask.title}"` : 'Create Task',
        href: `/dashboard/projects/${props.project.id}/tasks/create`,
    },
];

const form = useForm({
    title: '',
    description: '',
    parent_id: props.parentTask?.id?.toString() || '',
    priority: 'medium',
    status: 'pending',
    due_date: '',
});

const isSubmitting = ref(false);
const isGeneratingBreakdown = ref(false);
const suggestedSubtasks = ref<any[]>([]);
const aiNotes = ref<string[]>([]);
const showBreakdownResults = ref(false);

const submit = () => {
    if (form.processing) return;

    isSubmitting.value = true;

    form.post(`/dashboard/projects/${props.project.id}/tasks`, {
        onFinish: () => {
            isSubmitting.value = false;
        },
        onSuccess: () => {
            form.reset();
        },
    });
};

const generateAIBreakdown = async () => {
    if (!form.title.trim()) {
        alert('Please enter a task title before generating AI breakdown.');
        return;
    }

    isGeneratingBreakdown.value = true;
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    showBreakdownResults.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: form.title,
                description: form.description,
            }),
        });

        const data = await response.json();

        if (data.success) {
            suggestedSubtasks.value = data.subtasks || [];
            aiNotes.value = data.notes || [];
            showBreakdownResults.value = true;
        } else {
            alert(data.error || 'Failed to generate task breakdown. Please try again.');
        }
    } catch (error) {
        console.error('AI breakdown error:', error);
        alert('Failed to generate task breakdown. Please check your connection and try again.');
    } finally {
        isGeneratingBreakdown.value = false;
    }
};

const createTaskWithSubtasks = async () => {
    if (form.processing || isSubmitting.value) return;

    isSubmitting.value = true;

    try {
        // Create the main task with the subtasks array
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: form.title,
                description: form.description,
                parent_id: form.parent_id || null,
                priority: form.priority,
                status: form.status,
                due_date: form.due_date || null,
                subtasks: suggestedSubtasks.value,
            }),
        });

        if (response.ok) {
            // Redirect to tasks index
            window.location.href = `/dashboard/projects/${props.project.id}/tasks`;
        } else {
            alert('Failed to create task with subtasks. Please try again.');
        }
    } catch (error) {
        console.error('Task creation error:', error);
        alert('Failed to create task. Please try again.');
    } finally {
        isSubmitting.value = false;
    }
};

const clearBreakdown = () => {
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    showBreakdownResults.value = false;
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        submit();
    }
};
</script>

<template>
    <Head title="Create Task" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="space-y-6">
                <!-- Header with back button -->
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/dashboard/projects/${project.id}/tasks`" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Tasks
                        </Link>
                    </Button>
                    <div>
                        <Heading>Create New Task</Heading>
                        <p class="text-sm text-gray-600 mt-1">
                            Add a new task to {{ project.description }}
                        </p>
                    </div>
                </div>

                <!-- Create form -->
                <div class="max-w-2xl">
                    <Card>
                        <CardHeader class="text-center">
                            <CardTitle class="flex items-center justify-center gap-2">
                                <Plus class="h-5 w-5 text-blue-600" />
                                Create New Task
                            </CardTitle>
                            <CardDescription>
                                Add a task to help organize your project work
                            </CardDescription>
                        </CardHeader>

                        <form @submit.prevent="submit">
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title">Task Title</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="e.g., Set up project structure"
                                        :disabled="form.processing"
                                        @keydown="handleKeydown"
                                        required
                                    />
                                    <InputError :message="form.errors.title" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description (Optional)</Label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe what needs to be done..."
                                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                        :disabled="form.processing"
                                        @keydown="handleKeydown"
                                    ></textarea>
                                    <InputError :message="form.errors.description" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="due_date">Due Date (Optional)</Label>
                                    <Input
                                        id="due_date"
                                        v-model="form.due_date"
                                        type="date"
                                        :disabled="form.processing"
                                    />
                                    <InputError :message="form.errors.due_date" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="priority">Priority</Label>
                                        <select
                                            id="priority"
                                            v-model="form.priority"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="form.processing"
                                        >
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                        <InputError :message="form.errors.priority" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="status">Status</Label>
                                        <select
                                            id="status"
                                            v-model="form.status"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="form.processing"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                        <InputError :message="form.errors.status" />
                                    </div>
                                </div>

                                <!-- Parent Task Selection -->
                                <div v-if="parentTask || parentTasks.length > 0" class="space-y-2">
                                    <Label for="parent_id">Parent Task</Label>
                                    <div v-if="parentTask" class="p-3 bg-blue-50 border border-blue-200 rounded-md">
                                        <p class="text-sm font-medium text-blue-900">Creating subtask for:</p>
                                        <p class="text-sm text-blue-700">{{ parentTask.title }}</p>
                                    </div>
                                    <select
                                        v-else
                                        id="parent_id"
                                        v-model="form.parent_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="form.processing"
                                    >
                                        <option value="">None (Top-level task)</option>
                                        <option v-for="parentTaskOption in parentTasks" :key="parentTaskOption.id" :value="parentTaskOption.id">
                                            {{ parentTaskOption.title }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.parent_id" />
                                    <p class="text-xs text-gray-500">
                                        {{ parentTask ? 'This will be created as a subtask' : 'Select a parent task to create a subtask' }}
                                    </p>
                                </div>

                                <!-- AI Task Breakdown -->
                                <div class="space-y-4 pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">AI Task Breakdown</h4>
                                            <p class="text-xs text-gray-500">Let AI break this task into smaller subtasks</p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="generateAIBreakdown"
                                            :disabled="!form.title.trim() || isGeneratingBreakdown"
                                            class="flex items-center gap-2"
                                        >
                                            <Loader v-if="isGeneratingBreakdown" class="h-4 w-4 animate-spin" />
                                            <Sparkles v-else class="h-4 w-4" />
                                            {{ isGeneratingBreakdown ? 'Generating...' : 'Generate Subtasks' }}
                                        </Button>
                                    </div>

                                    <!-- AI Breakdown Results -->
                                    <div v-if="showBreakdownResults" class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-medium text-blue-900">AI Suggested Subtasks</h5>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="clearBreakdown"
                                                class="text-blue-600 hover:text-blue-700"
                                            >
                                                Clear
                                            </Button>
                                        </div>

                                        <!-- AI Notes -->
                                        <div v-if="aiNotes.length > 0" class="space-y-2">
                                            <h6 class="text-xs font-medium text-blue-800">AI Analysis:</h6>
                                            <ul class="text-xs text-blue-700 space-y-1">
                                                <li v-for="note in aiNotes" :key="note" class="flex items-start gap-2">
                                                    <span class="text-blue-400">•</span>
                                                    <span>{{ note }}</span>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Suggested Subtasks -->
                                        <div v-if="suggestedSubtasks.length > 0" class="space-y-3">
                                            <h6 class="text-xs font-medium text-blue-800">Suggested Subtasks ({{ suggestedSubtasks.length }}):</h6>
                                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                                <div
                                                    v-for="(subtask, index) in suggestedSubtasks"
                                                    :key="index"
                                                    class="p-3 bg-white border border-blue-100 rounded-md hover:border-blue-200 transition-colors"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="flex-1 min-w-0">
                                                            <h6 class="text-sm font-medium text-gray-900 truncate">{{ subtask.title }}</h6>
                                                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ subtask.description }}</p>
                                                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100">
                                                                    {{ subtask.priority }}
                                                                </span>
                                                                <span v-if="subtask.due_date">Due: {{ subtask.due_date }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex gap-2 pt-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    @click="createTaskWithSubtasks"
                                                    :disabled="isSubmitting"
                                                    class="flex-1"
                                                >
                                                    Create Task with Subtasks
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    @click="generateAIBreakdown"
                                                    :disabled="isGeneratingBreakdown"
                                                >
                                                    Regenerate
                                                </Button>
                                            </div>
                                        </div>

                                        <div v-else class="text-center py-4">
                                            <p class="text-sm text-blue-600">No subtasks generated. Try regenerating or create the task manually.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-xs text-gray-500 pt-2">
                                    Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 rounded">⌘ + Enter</kbd> to create
                                </div>
                            </CardContent>

                            <CardFooter class="flex gap-3">
                                <Button
                                    type="submit"
                                    class="flex-1 flex items-center gap-2"
                                    :disabled="form.processing || !form.title.trim()"
                                >
                                    <Send v-if="!isSubmitting" class="h-4 w-4" />
                                    <div v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                                    {{ isSubmitting ? 'Creating...' : 'Create Task' }}
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link :href="`/dashboard/projects/${project.id}/tasks`">Cancel</Link>
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
kbd {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
}
</style>
