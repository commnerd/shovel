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
                        <Heading title="AI Task Breakdown" class="mb-1" />
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
                        <!-- Consolidated AI Analysis -->
                        <div v-if="aiCommunication.summary || aiCommunication.notes?.length || aiCommunication.problems?.length || aiCommunication.suggestions?.length" class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
                            <h4 class="text-sm font-medium text-slate-800 mb-3 flex items-center gap-2">
                                <Lightbulb class="h-4 w-4" />
                                Notes & Analysis
                            </h4>

                            <div class="space-y-3">
                                <!-- Summary -->
                                <div v-if="aiCommunication.summary" class="pb-2">
                                    <h5 class="text-xs font-semibold text-blue-800 mb-1 flex items-center gap-1">
                                        <Info class="h-3 w-3" />
                                        Summary
                                    </h5>
                                    <p class="text-sm text-slate-700 pl-4">{{ aiCommunication.summary }}</p>
                                </div>

                                <!-- Notes -->
                                <div v-if="aiCommunication.notes?.length" class="pb-2">
                                    <h5 class="text-xs font-semibold text-slate-800 mb-1 flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        Notes
                                    </h5>
                                    <ul class="text-sm text-slate-700 space-y-1 pl-4">
                                        <li v-for="note in aiCommunication.notes" :key="note" class="flex items-start gap-2">
                                            <span class="text-slate-400 mt-1">•</span>
                                            <span>{{ note }}</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Issues -->
                                <div v-if="aiCommunication.problems?.length" class="pb-2">
                                    <h5 class="text-xs font-semibold text-orange-800 mb-1 flex items-center gap-1">
                                        <AlertTriangle class="h-3 w-3" />
                                        Issues
                                    </h5>
                                    <ul class="text-sm text-slate-700 space-y-1 pl-4">
                                        <li v-for="problem in aiCommunication.problems" :key="problem" class="flex items-start gap-2">
                                            <AlertTriangle class="h-3 w-3 mt-0.5 text-orange-600 flex-shrink-0" />
                                            <span>{{ problem }}</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Suggestions -->
                                <div v-if="aiCommunication.suggestions?.length">
                                    <h5 class="text-xs font-semibold text-green-800 mb-1 flex items-center gap-1">
                                        <Lightbulb class="h-3 w-3" />
                                        Suggestions
                                    </h5>
                                    <ul class="text-sm text-slate-700 space-y-1 pl-4">
                                        <li v-for="suggestion in aiCommunication.suggestions" :key="suggestion" class="flex items-start gap-2">
                                            <Lightbulb class="h-3 w-3 mt-0.5 text-green-600 flex-shrink-0" />
                                            <span>{{ suggestion }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
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
                                v-if="promptData"
                                @click="showPromptModal = true"
                                variant="outline"
                                size="lg"
                                :disabled="isCreatingSubtasks"
                                class="flex items-center gap-2"
                            >
                                <Eye class="h-4 w-4" />
                                View Prompt
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

        <!-- Prompt Viewing Modal -->
        <div v-if="showPromptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">AI Prompt Details</h3>
                    <Button
                        @click="closePromptModal"
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div v-if="promptData" class="space-y-6">
                        <!-- AI Configuration -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">AI Configuration</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-600">Provider:</span>
                                        <span class="ml-2 text-gray-900">{{ promptData.provider }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Model:</span>
                                        <span class="ml-2 text-gray-900">{{ promptData.model }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Task Information -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Task Information</h4>
                            <div class="bg-blue-50 p-4 rounded-lg space-y-2">
                                <div>
                                    <span class="font-medium text-blue-800">Title:</span>
                                    <span class="ml-2 text-blue-900">{{ promptData.task_title }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-blue-800">Description:</span>
                                    <span class="ml-2 text-blue-900">{{ promptData.task_description }}</span>
                                </div>
                                <div v-if="promptData.user_feedback !== 'No specific feedback provided'">
                                    <span class="font-medium text-blue-800">User Feedback:</span>
                                    <span class="ml-2 text-blue-900">{{ promptData.user_feedback }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Project Context -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Project Context</h4>
                            <div class="bg-green-50 p-4 rounded-lg space-y-2">
                                <div>
                                    <span class="font-medium text-green-800">Project:</span>
                                    <span class="ml-2 text-green-900">{{ promptData.project_context.title }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-green-800">Description:</span>
                                    <span class="ml-2 text-green-900">{{ promptData.project_context.description }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="font-medium text-green-800">Total Tasks:</span>
                                        <span class="ml-2 text-green-900">{{ promptData.project_context.total_tasks }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-green-800">Completed:</span>
                                        <span class="ml-2 text-green-900">{{ promptData.project_context.completed_tasks }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Parent Task (if applicable) -->
                        <div v-if="promptData.parent_task">
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Parent Task Context</h4>
                            <div class="bg-purple-50 p-4 rounded-lg space-y-2">
                                <div>
                                    <span class="font-medium text-purple-800">Title:</span>
                                    <span class="ml-2 text-purple-900">{{ promptData.parent_task.title }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Existing Tasks Sample -->
                        <div v-if="promptData.sample_existing_tasks?.length">
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">
                                Sample Existing Tasks ({{ promptData.existing_tasks_count }} total)
                            </h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <ul class="space-y-2">
                                    <li v-for="task in promptData.sample_existing_tasks" :key="task.title" class="text-sm">
                                        <span class="font-medium">{{ task.title }}</span>
                                        <span class="ml-2 text-gray-600">({{ task.status }})</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Full Prompt Section -->
                        <div v-if="fullPromptText" class="border-t pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-800">Full AI Prompt</h4>
                                <Button
                                    @click="showFullPrompt = !showFullPrompt"
                                    variant="outline"
                                    size="sm"
                                    class="flex items-center gap-2"
                                >
                                    <Eye class="h-3 w-3" />
                                    {{ showFullPrompt ? 'Hide' : 'Show' }} Full Prompt
                                </Button>
                            </div>

                            <div v-if="showFullPrompt" class="space-y-4">
                                <!-- System Prompt -->
                                <div v-if="fullPromptText.system_prompt">
                                    <h5 class="text-xs font-semibold text-red-800 mb-2 flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        System Prompt
                                    </h5>
                                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                        <pre class="text-xs text-red-900 whitespace-pre-wrap font-mono leading-relaxed">{{ fullPromptText.system_prompt }}</pre>
                                    </div>
                                </div>

                                <!-- User Prompt -->
                                <div v-if="fullPromptText.user_prompt">
                                    <h5 class="text-xs font-semibold text-blue-800 mb-2 flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        User Prompt
                                    </h5>
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <pre class="text-xs text-blue-900 whitespace-pre-wrap font-mono leading-relaxed">{{ fullPromptText.user_prompt }}</pre>
                                    </div>
                                </div>

                                <!-- Messages Format (for developers) -->
                                <div v-if="fullPromptText.messages?.length" class="bg-gray-100 p-4 rounded-lg">
                                    <h5 class="text-xs font-semibold text-gray-800 mb-2">API Messages Format</h5>
                                    <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ JSON.stringify(fullPromptText.messages, null, 2) }}</pre>
                                </div>

                                <!-- Error or Note -->
                                <div v-if="fullPromptText.error || fullPromptText.note" class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800">
                                        {{ fullPromptText.error || fullPromptText.note }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        No prompt data available
                    </div>
                </div>
            </div>
        </div>
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
    Info,
    MessageSquare,
    AlertTriangle,
    Eye,
    X,
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
const aiCommunication = ref<{
    summary?: string;
    notes?: string[];
    problems?: string[];
    suggestions?: string[];
}>({});
const hasGeneratedBreakdown = ref(false);

// Modal state
const showRegenerationModal = ref(false);
const showPromptModal = ref(false);
const promptData = ref<any>(null);
const fullPromptText = ref<any>(null);
const showFullPrompt = ref(false);

// Helper function to get CSRF token with error handling and refresh capability
const getCSRFToken = async (): Promise<string> => {
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!token) {
        // Try to refresh the token by making a request to a simple endpoint
        try {
            const response = await fetch('/dashboard', {
                method: 'GET',
                credentials: 'same-origin'
            });
            if (response.ok) {
                // Token should be refreshed in the meta tag now
                token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            }
        } catch (e) {
            console.error('Failed to refresh CSRF token:', e);
        }
    }

    if (!token) {
        throw new Error('CSRF token not found. Please refresh the page and try again.');
    }

    return token;
};

// Helper functions for task display
const getTaskTypeIcon = (task: Task) => {
    if (task.is_top_level && !task.has_children) return TreePine;
    if (task.has_children) return GitBranch;
    return Leaf;
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
    aiCommunication.value = {};
    hasGeneratedBreakdown.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': await getCSRFToken(),
            },
            body: JSON.stringify({
                title: props.task.title,
                description: props.task.description || '',
                parent_task_id: props.task.id,
            }),
        });

        // Handle CSRF errors specifically
        if (response.status === 419) {
            alert('Your session has expired. Please refresh the page and try again.');
            window.location.reload();
            return;
        }

        const data = await response.json();

        if (data.success) {
            suggestedSubtasks.value = data.subtasks || [];
            aiCommunication.value = {
                summary: data.summary,
                notes: data.notes || [],
                problems: data.problems || [],
                suggestions: data.suggestions || [],
            };


            // Store prompt data for viewing
            promptData.value = data.prompt_used || null;
            fullPromptText.value = data.full_prompt_text || null;

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
    aiCommunication.value = {};
    hasGeneratedBreakdown.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${props.project.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': await getCSRFToken(),
            },
            body: JSON.stringify({
                title: props.task.title,
                description: props.task.description || '',
                user_feedback: feedback,
                parent_task_id: props.task.id,
            }),
        });

        // Handle CSRF errors specifically
        if (response.status === 419) {
            alert('Your session has expired. Please refresh the page and try again.');
            window.location.reload();
            return;
        }

        const data = await response.json();

        if (data.success) {
            suggestedSubtasks.value = data.subtasks || [];
            aiCommunication.value = {
                summary: data.summary,
                notes: data.notes || [],
                problems: data.problems || [],
                suggestions: data.suggestions || [],
            };


            // Store prompt data for viewing
            promptData.value = data.prompt_used || null;
            fullPromptText.value = data.full_prompt_text || null;

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
            const response = await fetch(`/dashboard/projects/${props.project.id}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': await getCSRFToken(),
                },
                body: JSON.stringify({
                    title: subtask.title,
                    description: subtask.description || '',
                    parent_id: props.task.id,
                    status: subtask.status,
                    due_date: subtask.due_date || null,
                }),
            });

            // Handle CSRF errors specifically
            if (response.status === 419) {
                alert('Your session has expired. Please refresh the page and try again.');
                window.location.reload();
                return;
            }

            // Check if the request was successful (redirect or success status)
            if (!response.ok && response.status !== 302) {
                throw new Error(`Failed to create subtask "${subtask.title}". Status: ${response.status}`);
            }
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
    aiCommunication.value = {};
    hasGeneratedBreakdown.value = false;
};

const cancelRegeneration = () => {
    showRegenerationModal.value = false;
};

const closePromptModal = () => {
    showPromptModal.value = false;
    showFullPrompt.value = false; // Reset full prompt visibility when modal closes
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
