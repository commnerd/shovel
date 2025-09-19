<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import RegenerationFeedbackModal from '@/components/RegenerationFeedbackModal.vue';
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
    Info
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

export interface TaskSuggestion {
    title: string;
    description: string;
    status: 'pending' | 'in_progress' | 'completed';
    priority: 'low' | 'medium' | 'high';
    sort_order: number;
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
    };
    suggestedTasks: TaskSuggestion[];
    aiUsed: boolean;
    aiCommunication?: AICommunication | null;
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
    {
        title: 'Create Project',
        href: '/dashboard/projects/create',
    },
    {
        title: 'Review Tasks',
        href: '/dashboard/projects/create/tasks',
    },
];

const form = useForm({
    title: props.projectData.title || '',
    description: props.projectData.description,
    due_date: props.projectData.due_date || '',
    group_id: props.projectData.group_id || props.defaultGroupId,
    tasks: [...props.suggestedTasks] as TaskSuggestion[],
});

const editingTaskIndex = ref<number | null>(null);
const newTaskTitle = ref('');
const newTaskDescription = ref('');
const isRegenerating = ref(false);

// Regeneration modal state
const showRegenerationModal = ref(false);
const isRegeneratingWithFeedback = ref(false);

// Priority colors
const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'destructive';
        case 'medium': return 'default';
        case 'low': return 'secondary';
        default: return 'default';
    }
};

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

const saveTask = (index: number) => {
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
            priority: 'medium',
            sort_order: form.tasks.length + 1,
        });
        newTaskTitle.value = '';
        newTaskDescription.value = '';
    }
};

const toggleTaskStatus = (index: number) => {
    const task = form.tasks[index];
    const statuses = ['pending', 'in_progress', 'completed'];
    const currentIndex = statuses.indexOf(task.status);
    task.status = statuses[(currentIndex + 1) % statuses.length] as any;
};

const changePriority = (index: number) => {
    const task = form.tasks[index];
    const priorities = ['low', 'medium', 'high'];
    const currentIndex = priorities.indexOf(task.priority);
    task.priority = priorities[(currentIndex + 1) % priorities.length] as any;
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

const goBackToEdit = () => {
    router.visit('/dashboard/projects/create', {
        method: 'get',
        data: {
            title: form.title,
            description: form.description,
            due_date: form.due_date,
            group_id: form.group_id,
        }
    });
};

const createProject = () => {
    form.post('/dashboard/projects', {
        onSuccess: () => {
            // Project created successfully
        },
        onError: (errors) => {
            console.error('Project creation failed:', errors);
        }
    });
};
</script>

<template>
    <Head title="Review AI Generated Tasks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Heading class="flex items-center gap-2">
                        <Sparkles class="h-6 w-6 text-blue-600" />
                        AI Generated Tasks
                    </Heading>
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

            <!-- AI Communication -->
            <div v-if="aiUsed && aiCommunication && (aiCommunication.summary || aiCommunication.notes?.length || aiCommunication.problems?.length || aiCommunication.suggestions?.length)" class="space-y-4">

                <!-- AI Summary -->
                <Card v-if="aiCommunication.summary" class="border-blue-200 bg-blue-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-blue-800">
                            <Info class="h-5 w-5" />
                            AI Analysis Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-blue-700">{{ aiCommunication.summary }}</p>
                    </CardContent>
                </Card>

                <!-- AI Notes -->
                <Card v-if="aiCommunication.notes?.length" class="border-gray-200 bg-gray-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-gray-800">
                            <MessageSquare class="h-5 w-5" />
                            AI Notes
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="note in aiCommunication.notes" :key="note" class="flex items-start gap-2 text-gray-700">
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400 mt-2 flex-shrink-0"></div>
                                <span>{{ note }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- AI Problems -->
                <Card v-if="aiCommunication.problems?.length" class="border-orange-200 bg-orange-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-orange-800">
                            <AlertTriangle class="h-5 w-5" />
                            Issues Identified
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="problem in aiCommunication.problems" :key="problem" class="flex items-start gap-2 text-orange-700">
                                <AlertTriangle class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                <span>{{ problem }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- AI Suggestions -->
                <Card v-if="aiCommunication.suggestions?.length" class="border-green-200 bg-green-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-green-800">
                            <Lightbulb class="h-5 w-5" />
                            AI Suggestions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="suggestion in aiCommunication.suggestions" :key="suggestion" class="flex items-start gap-2 text-green-700">
                                <Lightbulb class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                <span>{{ suggestion }}</span>
                            </li>
                        </ul>
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
                                            @keydown.enter="saveTask(index)"
                                            @keydown.escape="editingTaskIndex = null"
                                        />
                                        <textarea
                                            v-model="task.description"
                                            class="w-full px-2 py-1 border rounded text-sm resize-none"
                                            rows="3"
                                            placeholder="Task description"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <Button size="sm" @click="saveTask(index)">
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
                                        <p class="text-base text-gray-700 mb-4 leading-relaxed">{{ task.description }}</p>
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <Badge
                                                :variant="getPriorityColor(task.priority)"
                                                class="cursor-pointer px-3 py-1"
                                                @click="changePriority(index)"
                                                :data-testid="`task-priority-${index}`"
                                            >
                                                {{ task.priority.toUpperCase() }} PRIORITY
                                            </Badge>
                                            <Badge variant="outline" class="px-3 py-1">
                                                {{ task.status.replace('_', ' ').toUpperCase() }}
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
    </AppLayout>
</template>
