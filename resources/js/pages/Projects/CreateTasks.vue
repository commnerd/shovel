<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import RegenerationFeedbackModal from '@/components/RegenerationFeedbackModal.vue';
import TaskSizing from '@/components/TaskSizing.vue';
import {
    ArrowLeft,
    Sparkles,
    CheckCircle2,
    Circle,
    AlertCircle,
    Edit3,
    Trash2,
    Plus,
    Save,
    RotateCcw,
    MessageSquare,
    Lightbulb,
    AlertTriangle,
    Info,
    X
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

export interface TaskSuggestion {
    title: string;
    description: string;
    status: 'pending' | 'in_progress' | 'completed';
    sort_order: number;
    // Iterative project fields
    size?: 'xs' | 's' | 'm' | 'l' | 'xl' | null;
    initial_story_points?: number;
    current_story_points?: number;
    story_points_change_count?: number;
    // Required for TaskSizing component
    id?: number;
    depth?: number;
}

interface AICommunication {
    summary?: string | null;
    notes?: string[];
    problems?: string[];
    suggestions?: string[];
}

interface Group {
    id: number;
    name: string;
    description?: string;
    is_default: boolean;
    organization_name: string;
}

interface Props {
    projectData: {
        title?: string;
        description: string;
        due_date?: string;
        group_id?: number;
        ai_provider?: string;
        ai_model?: string;
        project_type?: string;
        default_iteration_length_weeks?: number;
        auto_create_iterations?: boolean;
        project_id?: number;
    };
    project?: {
        id: number;
        title: string;
        description: string;
        due_date?: string;
        status: string;
        project_type: string;
    } | null;
    returnUrl?: string;
    suggestedTasks: TaskSuggestion[];
    aiUsed: boolean;
    aiCommunication?: AICommunication | null;
    promptData?: any | null;
    fullPromptText?: any | null;
    userGroups: Group[];
    defaultGroupId?: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
    },
    ...(props.project ? [{
        title: props.project.title,
        href: `/dashboard/projects/${props.project.id}/tasks`,
    }] : [{
        title: 'Create Project',
        href: '/dashboard/projects/create',
    }]),
    {
        title: 'Generate Tasks',
        href: '#',
    },
];

const form = useForm({
    title: props.projectData.title || '',
    description: props.projectData.description || '',
    due_date: props.projectData.due_date || '',
    group_id: props.projectData.group_id || props.defaultGroupId || null,
    ai_provider: props.projectData.ai_provider || null,
    ai_model: props.projectData.ai_model || null,
    project_type: props.projectData.project_type || 'iterative',
    default_iteration_length_weeks: props.projectData.default_iteration_length_weeks || null,
    auto_create_iterations: props.projectData.auto_create_iterations || false,
    tasks: [...props.suggestedTasks] as TaskSuggestion[],
});

// Debug: Log the form initialization values
console.log('Form initialized with:', {
    title: form.title,
    description: form.description,
    projectData: props.projectData,
    defaultGroupId: props.defaultGroupId
});

const editingTaskIndex = ref<number | null>(null);
const newTaskTitle = ref('');
const newTaskDescription = ref('');

// Regeneration modal state
const showRegenerationModal = ref(false);
const isRegeneratingWithFeedback = ref(false);

// Prompt modal state
const showPromptModal = ref(false);
const showFullPrompt = ref(false);
const showAIResponse = ref(false);


// Status icons
const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle2;
        case 'in_progress': return AlertCircle;
        default: return Circle;
    }
};

// Task actions
const editTask = (index: number) => {
    editingTaskIndex.value = index;
};

const saveTask = () => {
    editingTaskIndex.value = null;
};

const deleteTask = (index: number) => {
    form.tasks.splice(index, 1);
    // Update sort orders
    form.tasks.forEach((task, idx) => {
        task.sort_order = idx + 1;
    });
};

const addNewTask = () => {
    if (newTaskTitle.value.trim()) {
        form.tasks.push({
            title: newTaskTitle.value.trim(),
            description: newTaskDescription.value.trim(),
            status: 'pending',
            sort_order: form.tasks.length + 1,
        });
        newTaskTitle.value = '';
        newTaskDescription.value = '';
    }
};

const toggleTaskStatus = (index: number) => {
    const task = form.tasks[index];
    const statuses = ['pending', 'in_progress', 'completed'];
    const currentStatus = task.status || 'pending';
    const currentIndex = statuses.indexOf(currentStatus);
    task.status = statuses[(currentIndex + 1) % statuses.length] as any;
};


const regenerateTasks = () => {
    showRegenerationModal.value = true;
};

const regenerateTasksWithFeedback = (feedback: string) => {
    isRegeneratingWithFeedback.value = true;
    showRegenerationModal.value = false;

    router.visit('/dashboard/projects/create/tasks', {
        method: 'post',
        data: {
            title: form.title,
            description: form.description,
            due_date: form.due_date,
            group_id: form.group_id,
            ai_provider: form.ai_provider,
            ai_model: form.ai_model,
            regenerate: true,
            user_feedback: feedback,
        },
        onFinish: () => {
            isRegeneratingWithFeedback.value = false;
        }
    });
};

const cancelRegeneration = () => {
    showRegenerationModal.value = false;
};

const closePromptModal = () => {
    showPromptModal.value = false;
    showFullPrompt.value = false;
    showAIResponse.value = false;
};

const goBackToEdit = () => {
    router.visit('/dashboard/projects/create', {
        method: 'get',
        data: {
            title: form.title,
            description: form.description,
            due_date: form.due_date,
            group_id: form.group_id,
            ai_provider: form.ai_provider,
            ai_model: form.ai_model,
        }
    });
};

const createProject = () => {
    // Clean up form data to remove undefined values that might cause URL construction issues
    const cleanedTasks = form.tasks.map(task => ({
        title: task.title || '',
        description: task.description || '',
        status: task.status || 'pending',
        sort_order: task.sort_order || 1,
        // Ensure size is either null or a valid value
        size: task.size && ['xs', 's', 'm', 'l', 'xl'].includes(task.size) ? task.size : null,
        // Only include optional fields if they have valid values
        ...(task.initial_story_points !== undefined && { initial_story_points: task.initial_story_points }),
        ...(task.current_story_points !== undefined && { current_story_points: task.current_story_points }),
        ...(task.story_points_change_count !== undefined && { story_points_change_count: task.story_points_change_count }),
    }));

    // Update the form with cleaned data
    form.tasks = cleanedTasks;

    // Ensure required fields are valid before submission
    // Note: title is optional (backend will generate one if not provided)
    if (!form.description) {
        console.error('Missing required field:', {
            title: form.title,
            description: form.description,
            descriptionEmpty: !form.description
        });

        alert('Please provide a project description before creating the project.');
        return;
    }

    // Debug: Log the form data being sent
    console.log('Submitting project with data:', {
        title: form.title,
        description: form.description,
        due_date: form.due_date,
        group_id: form.group_id,
        ai_provider: form.ai_provider,
        ai_model: form.ai_model,
        project_type: form.project_type,
        default_iteration_length_weeks: form.default_iteration_length_weeks,
        auto_create_iterations: form.auto_create_iterations,
        tasksCount: form.tasks.length,
        tasks: form.tasks
    });

    // Submit using form.post
    form.transform((data) => ({
        ...data,
        return_url: props.returnUrl,
    })).post('/dashboard/projects', {
        onSuccess: () => {
            // Project created successfully
        },
        onError: (errors) => {
            console.error('Project creation failed:', errors);
        }
    });
};

// Refresh tasks data (for TaskSizing component updates)
const refreshTasks = () => {
    // In this context, we don't need to reload from server since tasks are local
    // The TaskSizing component will update the local task data
    // Force reactivity update
    form.tasks = [...form.tasks];
};
</script>

<template>
    <Head title="Review AI Generated Tasks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Heading title="AI Generated Tasks" class="flex items-center gap-2" />
                    <div v-if="projectData.title" class="mt-2">
                        <h2 class="text-xl font-semibold text-gray-900">{{ projectData.title }}</h2>
                    </div>
                    <p class="text-gray-600 mt-2">
                        <span v-if="!projectData.title">Review and customize the suggested tasks for: </span>
                        <span class="font-medium text-gray-900">{{ projectData.description }}</span>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ aiUsed ? 'Generated using AI' : 'Using fallback tasks (AI unavailable)' }}
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button variant="outline" @click="goBackToEdit">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Edit Description
                    </Button>
                    <Button
                        variant="outline"
                        @click="regenerateTasks"
                        :disabled="isRegeneratingWithFeedback"
                    >
                        <RotateCcw class="h-4 w-4 mr-2" :class="{ 'animate-spin': isRegeneratingWithFeedback }" />
                        {{ isRegeneratingWithFeedback ? 'Regenerating...' : 'Regenerate Tasks' }}
                    </Button>
                </div>
            </div>

            <!-- Consolidated AI Analysis Notes -->
            <div v-if="aiUsed && aiCommunication && (aiCommunication.summary || aiCommunication.notes?.length || aiCommunication.problems?.length || aiCommunication.suggestions?.length)">
                <Card class="border-slate-200 bg-slate-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-slate-800">
                            <MessageSquare class="h-5 w-5" />
                            Notes & Analysis
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- AI Summary -->
                        <div v-if="aiCommunication.summary" class="pb-3">
                            <h4 class="text-sm font-semibold text-blue-800 mb-2 flex items-center gap-2">
                                <Info class="h-4 w-4" />
                                Summary
                            </h4>
                            <p class="text-slate-700 pl-6">{{ aiCommunication.summary }}</p>
                        </div>

                        <!-- AI Notes -->
                        <div v-if="aiCommunication.notes?.length" class="pb-3">
                            <h4 class="text-sm font-semibold text-slate-800 mb-2 flex items-center gap-2">
                                <MessageSquare class="h-4 w-4" />
                                Notes
                            </h4>
                            <ul class="space-y-1 pl-6">
                                <li v-for="note in aiCommunication.notes" :key="note" class="flex items-start gap-2 text-slate-700">
                                    <div class="w-1.5 h-1.5 rounded-full bg-slate-400 mt-2 flex-shrink-0"></div>
                                    <span>{{ note }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Issues Identified -->
                        <div v-if="aiCommunication.problems?.length" class="pb-3">
                            <h4 class="text-sm font-semibold text-orange-800 mb-2 flex items-center gap-2">
                                <AlertTriangle class="h-4 w-4" />
                                Issues Identified
                            </h4>
                            <ul class="space-y-1 pl-6">
                                <li v-for="problem in aiCommunication.problems" :key="problem" class="flex items-start gap-2 text-slate-700">
                                    <AlertTriangle class="h-3 w-3 mt-1 flex-shrink-0 text-orange-600" />
                                    <span>{{ problem }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- AI Suggestions -->
                        <div v-if="aiCommunication.suggestions?.length">
                            <h4 class="text-sm font-semibold text-green-800 mb-2 flex items-center gap-2">
                                <Lightbulb class="h-4 w-4" />
                                Suggestions
                            </h4>
                            <ul class="space-y-1 pl-6">
                                <li v-for="suggestion in aiCommunication.suggestions" :key="suggestion" class="flex items-start gap-2 text-slate-700">
                                    <Lightbulb class="h-3 w-3 mt-1 flex-shrink-0 text-green-600" />
                                    <span>{{ suggestion }}</span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Task List -->
            <div class="flex-1 overflow-hidden">
                <div class="h-full overflow-y-auto">
                    <div class="space-y-4 pb-6 max-w-none">
                        <!-- Task Cards -->
                        <Card
                            v-for="(task, index) in form.tasks"
                            :key="index"
                            class="p-6 w-full border hover:shadow-md transition-shadow"
                            :data-testid="`task-${index}`"
                        >
                            <div class="flex items-start gap-4 w-full">
                                <!-- Status Icon -->
                                <button
                                    @click="toggleTaskStatus(index)"
                                    class="mt-1 transition-colors hover:text-blue-600 flex-shrink-0"
                                    :data-testid="`task-status-${index}`"
                                >
                                    <component :is="getStatusIcon(task.status)" class="h-6 w-6" />
                                </button>

                                <!-- Task Content -->
                                <div class="flex-1 min-w-0 w-full">
                                    <!-- Edit Mode -->
                                    <div v-if="editingTaskIndex === index" class="space-y-2">
                                        <input
                                            v-model="task.title"
                                            class="w-full px-2 py-1 border rounded text-sm font-medium"
                                            placeholder="Task title"
                                            @keydown.enter="saveTask()"
                                            @keydown.escape="editingTaskIndex = null"
                                        />
                                        <textarea
                                            v-model="task.description"
                                            class="w-full px-2 py-1 border rounded text-sm resize-none"
                                            rows="3"
                                            placeholder="Task description"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <Button size="sm" @click="saveTask()">
                                                <Save class="h-3 w-3 mr-1" />
                                                Save
                                            </Button>
                                            <Button size="sm" variant="outline" @click="editingTaskIndex = null">
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Display Mode -->
                                    <div v-else class="w-full">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-3 leading-tight">{{ task.title }}</h3>
                                        <div class="mb-3">
                                            <TaskSizing :task="{ ...task, id: task.id || 0, depth: task.depth || 0, size: task.size || undefined }" @updated="refreshTasks" />
                                        </div>
                                        <p class="text-base text-gray-700 mb-4 leading-relaxed">{{ task.description }}</p>
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <Badge variant="outline" class="px-3 py-1">
                                                {{ (task.status || 'pending').replace('_', ' ').toUpperCase() }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2 flex-shrink-0">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        @click="editTask(index)"
                                        :data-testid="`edit-task-${index}`"
                                        class="flex items-center gap-2"
                                    >
                                        <Edit3 class="h-4 w-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        @click="deleteTask(index)"
                                        :data-testid="`delete-task-${index}`"
                                        class="flex items-center gap-2 text-red-600 hover:text-red-700 border-red-200 hover:border-red-300"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        </Card>

                        <!-- Add New Task Card -->
                        <Card class="p-6 w-full border-dashed border-gray-300 bg-gray-50/50 hover:bg-gray-50 transition-colors">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 mb-4">
                                    <Plus class="h-5 w-5 text-gray-400" />
                                    <h3 class="text-lg font-medium text-gray-700">Add New Task</h3>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Task Title</label>
                                        <input
                                            v-model="newTaskTitle"
                                            class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Enter task title..."
                                            @keydown.enter="addNewTask"
                                            data-testid="new-task-title"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Task Description</label>
                                        <textarea
                                            v-model="newTaskDescription"
                                            class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            rows="3"
                                            placeholder="Enter task description (optional)..."
                                            data-testid="new-task-description"
                                        ></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <Button
                                        @click="addNewTask"
                                        :disabled="!newTaskTitle.trim()"
                                        class="flex items-center gap-2 px-6 py-2"
                                        data-testid="add-task-button"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Add Task
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="border-t bg-gray-50 -mx-6 -mb-6 px-6 py-4">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        {{ form.tasks.length }} task{{ form.tasks.length !== 1 ? 's' : '' }} ready to import
                    </div>
                    <div class="flex gap-3">
                        <Button
                            variant="outline"
                            @click="goBackToEdit"
                            size="lg"
                        >
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Back to Edit Description
                        </Button>
                        <Button
                            v-if="props.promptData"
                            @click="showPromptModal = true"
                            variant="outline"
                            size="lg"
                            :disabled="form.processing"
                            class="flex items-center gap-2"
                        >
                            <MessageSquare class="h-4 w-4" />
                            View Prompt
                        </Button>
                        <Button
                            @click="createProject"
                            :disabled="form.processing || form.tasks.length === 0"
                            size="lg"
                            class="min-w-[200px]"
                        >
                            <Save class="h-4 w-4 mr-2" />
                            {{ form.processing ? 'Creating...' : `Create Project with ${form.tasks.length} Tasks` }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regeneration Feedback Modal -->
        <RegenerationFeedbackModal
            :open="showRegenerationModal"
            @update:open="showRegenerationModal = $event"
            task-type="tasks"
            :current-results="form.tasks"
            :context="{
                projectTitle: form.title || 'Untitled Project',
                existingTasksCount: form.tasks.length
            }"
            :is-processing="isRegeneratingWithFeedback"
            @regenerate="regenerateTasksWithFeedback"
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
                    <div v-if="props.promptData" class="space-y-6">
                        <!-- AI Configuration -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">AI Configuration</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-600">Provider:</span>
                                        <span class="ml-2 text-gray-900">{{ props.promptData.provider }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Model:</span>
                                        <span class="ml-2 text-gray-900">{{ props.promptData.model }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Information -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Project Information</h4>
                            <div class="bg-blue-50 p-4 rounded-lg space-y-2">
                                <div>
                                    <span class="font-medium text-blue-800">Description:</span>
                                    <span class="ml-2 text-blue-900">{{ props.promptData.project_description }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-blue-800">Project Type:</span>
                                    <span class="ml-2 text-blue-900">{{ props.promptData.project_type }}</span>
                                </div>
                                <div v-if="props.promptData.due_date">
                                    <span class="font-medium text-blue-800">Due Date:</span>
                                    <span class="ml-2 text-blue-900">{{ props.promptData.due_date }}</span>
                                </div>
                                <div v-if="props.promptData.user_feedback !== 'No specific feedback provided'">
                                    <span class="font-medium text-blue-800">User Feedback:</span>
                                    <span class="ml-2 text-blue-900">{{ props.promptData.user_feedback }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Iteration Settings (for iterative projects) -->
                        <div v-if="props.promptData.iteration_settings">
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Iteration Settings</h4>
                            <div class="bg-green-50 p-4 rounded-lg space-y-2">
                                <div>
                                    <span class="font-medium text-green-800">Default Iteration Length:</span>
                                    <span class="ml-2 text-green-900">{{ props.promptData.iteration_settings.default_iteration_length_weeks || 'Not set' }} weeks</span>
                                </div>
                                <div>
                                    <span class="font-medium text-green-800">Auto Create Iterations:</span>
                                    <span class="ml-2 text-green-900">{{ props.promptData.iteration_settings.auto_create_iterations ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Task Schema -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Expected Task Schema</h4>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <pre class="text-xs text-purple-900 whitespace-pre-wrap font-mono leading-relaxed">{{ JSON.stringify(props.promptData.task_schema, null, 2) }}</pre>
                            </div>
                        </div>

                        <!-- Full Prompt Section -->
                        <div v-if="props.fullPromptText" class="border-t pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-800">Full AI Prompt</h4>
                                <div class="flex items-center gap-2">
                                    <Button
                                        @click="showAIResponse = !showAIResponse"
                                        variant="outline"
                                        size="sm"
                                        class="flex items-center gap-2"
                                    >
                                        <Sparkles class="h-3 w-3" />
                                        {{ showAIResponse ? 'Hide' : 'Show' }} Response
                                    </Button>
                                    <Button
                                        @click="showFullPrompt = !showFullPrompt"
                                        variant="outline"
                                        size="sm"
                                        class="flex items-center gap-2"
                                    >
                                        <MessageSquare class="h-3 w-3" />
                                        {{ showFullPrompt ? 'Hide' : 'Show' }} Full Prompt
                                    </Button>
                                </div>
                            </div>

                            <div v-if="showFullPrompt" class="space-y-4">
                                <!-- System Prompt -->
                                <div v-if="props.fullPromptText.system_prompt">
                                    <h5 class="text-xs font-semibold text-red-800 mb-2 flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        System Prompt
                                    </h5>
                                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                        <pre class="text-xs text-red-900 whitespace-pre-wrap font-mono leading-relaxed">{{ props.fullPromptText.system_prompt }}</pre>
                                    </div>
                                </div>

                                <!-- User Prompt -->
                                <div v-if="props.fullPromptText.user_prompt">
                                    <h5 class="text-xs font-semibold text-blue-800 mb-2 flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        User Prompt
                                    </h5>
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <pre class="text-xs text-blue-900 whitespace-pre-wrap font-mono leading-relaxed">{{ props.fullPromptText.user_prompt }}</pre>
                                    </div>
                                </div>

                                <!-- Messages Format (for developers) -->
                                <div v-if="props.fullPromptText.messages?.length" class="bg-gray-100 p-4 rounded-lg">
                                    <h5 class="text-xs font-semibold text-gray-800 mb-2">API Messages Format</h5>
                                    <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ JSON.stringify(props.fullPromptText.messages, null, 2) }}</pre>
                                </div>

                                <!-- Error or Note -->
                                <div v-if="props.fullPromptText.error || props.fullPromptText.note" class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800">
                                        {{ props.fullPromptText.error || props.fullPromptText.note }}
                                    </p>
                                </div>
                            </div>

                            <!-- AI Response Section -->
                            <div v-if="showAIResponse" class="space-y-4 mt-6">
                                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <h5 class="text-xs font-semibold text-green-800 mb-3 flex items-center gap-1">
                                        <Sparkles class="h-3 w-3" />
                                        AI Response
                                    </h5>

                                    <!-- Raw Response JSON -->
                                    <div class="space-y-3">
                                        <div>
                                            <h6 class="text-xs font-medium text-green-700 mb-2">Generated Tasks:</h6>
                                            <pre class="text-xs bg-white p-3 rounded border overflow-x-auto text-gray-700">{{ JSON.stringify(props.suggestedTasks, null, 2) }}</pre>
                                        </div>

                                        <!-- AI Communication -->
                                        <div v-if="props.aiCommunication">
                                            <h6 class="text-xs font-medium text-green-700 mb-2">AI Communication:</h6>
                                            <div class="space-y-2">
                                                <div v-if="props.aiCommunication.summary" class="bg-white p-3 rounded border">
                                                    <span class="text-xs font-medium text-green-700">Summary:</span>
                                                    <p class="text-xs text-gray-700 mt-1">{{ props.aiCommunication.summary }}</p>
                                                </div>
                                                <div v-if="props.aiCommunication.notes && props.aiCommunication.notes.length > 0" class="bg-white p-3 rounded border">
                                                    <span class="text-xs font-medium text-green-700">Notes:</span>
                                                    <ul class="text-xs text-gray-700 mt-1 list-disc list-inside">
                                                        <li v-for="note in props.aiCommunication.notes" :key="note">{{ note }}</li>
                                                    </ul>
                                                </div>
                                                <div v-if="props.aiCommunication.problems && props.aiCommunication.problems.length > 0" class="bg-white p-3 rounded border">
                                                    <span class="text-xs font-medium text-green-700">Problems:</span>
                                                    <ul class="text-xs text-gray-700 mt-1 list-disc list-inside">
                                                        <li v-for="problem in props.aiCommunication.problems" :key="problem">{{ problem }}</li>
                                                    </ul>
                                                </div>
                                                <div v-if="props.aiCommunication.suggestions && props.aiCommunication.suggestions.length > 0" class="bg-white p-3 rounded border">
                                                    <span class="text-xs font-medium text-green-700">Suggestions:</span>
                                                    <ul class="text-xs text-gray-700 mt-1 list-disc list-inside">
                                                        <li v-for="suggestion in props.aiCommunication.suggestions" :key="suggestion">{{ suggestion }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
