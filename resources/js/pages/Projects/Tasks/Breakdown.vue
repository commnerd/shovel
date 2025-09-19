<template>
    <Head :title="`AI Breakdown - ${task.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/dashboard/projects/${project.id}/tasks`" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Tasks
                        </Link>
                    </Button>
                    <Separator orientation="vertical" class="h-6" />
                    <div>
                        <Heading class="mb-1">AI Task Breakdown</Heading>
                        <p class="text-sm text-gray-600">Let AI break down "{{ task.title }}" into manageable subtasks</p>
                    </div>
                </div>
            </div>

            <!-- Original Task Info -->
            <Card class="border-blue-200 bg-blue-50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-blue-900">
                        <component :is="getTaskTypeIcon(task)" class="h-5 w-5" />
                        {{ task.title }}
                    </CardTitle>
                    <CardDescription class="text-blue-700">
                        {{ task.description || 'No description provided' }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Priority:</span>
                            <span :class="getPriorityColor(task.priority)" class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium">
                                {{ task.priority }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Status:</span>
                            <span :class="getStatusColor(task.status)" class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium">
                                {{ task.status.replace('_', ' ') }}
                            </span>
                        </div>
                        <div v-if="task.due_date" class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-gray-500" />
                            <span>Due: {{ new Date(task.due_date).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- AI Generation Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-purple-600" />
                        AI Task Breakdown
                    </CardTitle>
                    <CardDescription>
                        Generate intelligent subtasks based on the task details and project context
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!hasGeneratedBreakdown" class="text-center py-8">
                        <div class="mb-6">
                            <div class="mx-auto w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-4">
                                <Sparkles class="h-8 w-8 text-purple-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Ready to Generate Subtasks</h3>
                            <p class="text-gray-600 max-w-md mx-auto">
                                AI will analyze "{{ task.title }}" along with your project context to suggest practical subtasks.
                            </p>
                        </div>

                        <Button
                            @click="generateBreakdown"
                            :disabled="isGenerating"
                            size="lg"
                            class="flex items-center gap-2"
                        >
                            <Loader v-if="isGenerating" class="h-5 w-5 animate-spin" />
                            <Sparkles v-else class="h-5 w-5" />
                            {{ isGenerating ? 'Generating Subtasks...' : 'Generate AI Subtasks' }}
                        </Button>
                    </div>

                    <!-- AI Results -->
                    <div v-else class="space-y-6">
                        <!-- AI Notes -->
                        <div v-if="aiNotes.length > 0" class="p-4 bg-green-50 border border-green-200 rounded-lg">
                            <h4 class="text-sm font-medium text-green-800 mb-2 flex items-center gap-2">
                                <Lightbulb class="h-4 w-4" />
                                AI Analysis
                            </h4>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li v-for="note in aiNotes" :key="note" class="flex items-start gap-2">
                                    <span class="text-green-400 mt-1">•</span>
                                    <span>{{ note }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Generated Subtasks -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">
                                    Generated Subtasks ({{ suggestedSubtasks.length }})
                                </h4>
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="showRegenerationModal = true"
                                        :disabled="isGenerating || isRegeneratingWithFeedback"
                                        class="flex items-center gap-2"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                        Regenerate
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="clearResults"
                                        :disabled="isGenerating || isRegeneratingWithFeedback"
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <Card
                                    v-for="(subtask, index) in suggestedSubtasks"
                                    :key="index"
                                    class="hover:shadow-md transition-shadow"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle class="text-base flex items-center gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium">
                                                {{ index + 1 }}
                                            </span>
                                            {{ subtask.title }}
                                        </CardTitle>
                                        <CardDescription>
                                            {{ subtask.description }}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div class="flex items-center gap-4 text-sm">
                                            <span :class="getPriorityColor(subtask.priority)" class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium">
                                                {{ subtask.priority }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium text-gray-600">
                                                {{ subtask.status }}
                                            </span>
                                            <span v-if="subtask.due_date" class="flex items-center gap-1 text-gray-500">
                                                <Calendar class="h-3 w-3" />
                                                {{ subtask.due_date }}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-4 pt-4 border-t">
                            <Button
                                @click="createAllSubtasks"
                                :disabled="isCreatingSubtasks"
                                size="lg"
                                class="flex-1 flex items-center gap-2"
                            >
                                <Loader v-if="isCreatingSubtasks" class="h-5 w-5 animate-spin" />
                                <Plus v-else class="h-5 w-5" />
                                {{ isCreatingSubtasks ? 'Creating Subtasks...' : `Create All ${suggestedSubtasks.length} Subtasks` }}
                            </Button>
                            <Button
                                variant="outline"
                                as-child
                                size="lg"
                                :disabled="isCreatingSubtasks"
                            >
                                <Link :href="`/dashboard/projects/${project.id}/tasks`">
                                    Cancel
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Regeneration Feedback Modal -->
        <RegenerationFeedbackModal
            :open="showRegenerationModal"
            @update:open="showRegenerationModal = $event"
            task-type="subtasks"
            :current-results="suggestedSubtasks"
            :context="{
                projectTitle: project.title,
                existingTasksCount: projectTaskCount,
                taskTitle: task.title
            }"
            :is-processing="isRegeneratingWithFeedback"
            @regenerate="regenerateWithFeedback"
            @cancel="cancelRegeneration"
        />
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import RegenerationFeedbackModal from '@/components/RegenerationFeedbackModal.vue';
import {
    ArrowLeft,
    Sparkles,
    Loader,
    Calendar,
    Plus,
    RotateCcw,
    Lightbulb,
    TreePine,
    Leaf,
    GitBranch,
    // Removed unused icons: CheckCircle, Clock, Circle
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Project {
    id: number;
    title: string;
    description: string;
}

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    priority: 'low' | 'medium' | 'high';
    due_date?: string;
    parent_id?: number;
    has_children: boolean;
    is_leaf: boolean;
    is_top_level: boolean;
    depth: number;
}

interface Props {
    project: Project;
    task: Task;
    projectTaskCount: number;
}

const props = defineProps<Props>();

// State management
const isGenerating = ref(false);
const isRegeneratingWithFeedback = ref(false);
const isCreatingSubtasks = ref(false);
const suggestedSubtasks = ref<any[]>([]);
const aiNotes = ref<string[]>([]);
const hasGeneratedBreakdown = ref(false);

// Modal state
const showRegenerationModal = ref(false);

// Helper functions for task display
const getTaskTypeIcon = (task: Task) => {
    if (task.is_top_level && !task.has_children) return TreePine;
    if (task.has_children) return GitBranch;
    return Leaf;
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'border-red-200 bg-red-50 text-red-700';
        case 'medium': return 'border-yellow-200 bg-yellow-50 text-yellow-700';
        case 'low': return 'border-green-200 bg-green-50 text-green-700';
        default: return 'border-gray-200 bg-gray-50 text-gray-700';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'border-green-200 bg-green-50 text-green-700';
        case 'in_progress': return 'border-blue-200 bg-blue-50 text-blue-700';
        case 'pending': return 'border-gray-200 bg-gray-50 text-gray-700';
        default: return 'border-gray-200 bg-gray-50 text-gray-700';
    }
};

// AI Breakdown functionality
const generateBreakdown = async () => {
    isGenerating.value = true;
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    hasGeneratedBreakdown.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: props.task.title,
                description: props.task.description || '',
            }),
        });

        const data = await response.json();

        if (data.success) {
            suggestedSubtasks.value = data.subtasks || [];
            aiNotes.value = data.notes || [];
            hasGeneratedBreakdown.value = true;
        } else {
            alert(data.error || 'Failed to generate task breakdown. Please try again.');
        }
    } catch (error) {
        console.error('AI breakdown error:', error);
        alert('Failed to generate task breakdown. Please check your connection and try again.');
    } finally {
        isGenerating.value = false;
    }
};

const regenerateWithFeedback = async (feedback: string) => {
    isRegeneratingWithFeedback.value = true;
    showRegenerationModal.value = false;
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    hasGeneratedBreakdown.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: props.task.title,
                description: props.task.description || '',
                user_feedback: feedback,
            }),
        });

        const data = await response.json();

        if (data.success) {
            suggestedSubtasks.value = data.subtasks || [];
            aiNotes.value = data.notes || [];
            hasGeneratedBreakdown.value = true;
        } else {
            alert(data.error || 'Failed to regenerate task breakdown. Please try again.');
        }
    } catch (error) {
        console.error('AI breakdown error:', error);
        alert('Failed to regenerate task breakdown. Please check your connection and try again.');
    } finally {
        isRegeneratingWithFeedback.value = false;
    }
};

const createAllSubtasks = async () => {
    if (suggestedSubtasks.value.length === 0) return;

    isCreatingSubtasks.value = true;

    try {
        // Create each subtask individually
        for (const subtask of suggestedSubtasks.value) {
            await fetch(`/dashboard/projects/${props.project.id}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    title: subtask.title,
                    description: subtask.description || '',
                    parent_id: props.task.id,
                    priority: subtask.priority,
                    status: subtask.status,
                    due_date: subtask.due_date || null,
                }),
            });
        }

        // Redirect back to tasks with success message
        router.visit(`/dashboard/projects/${props.project.id}/tasks`, {
            method: 'get',
            data: {
                message: `Successfully created ${suggestedSubtasks.value.length} subtasks for "${props.task.title}"!`
            }
        });
    } catch (error) {
        console.error('Subtask creation error:', error);
        alert('Failed to create subtasks. Please try again.');
    } finally {
        isCreatingSubtasks.value = false;
    }
};

const clearResults = () => {
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    hasGeneratedBreakdown.value = false;
};

const cancelRegeneration = () => {
    showRegenerationModal.value = false;
};

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
        title: 'AI Breakdown',
        href: '#',
    },
];
</script>
