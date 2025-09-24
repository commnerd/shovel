<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, nextTick, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Filter, CheckCircle, Clock, Circle, Calendar, Users, Layers, Plus, Edit, Trash2, TreePine, Leaf, GitBranch, Sparkles, GripVertical, AlertTriangle, List, BarChart3, CheckSquare, Kanban } from 'lucide-vue-next';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { BreadcrumbItem } from '@/types';

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    parent_id?: number;
    due_date?: string;
    size?: 'xs' | 's' | 'm' | 'l' | 'xl';
    current_story_points?: number;
    project: {
        id: number;
        title: string;
        project_type: string;
    };
    parent?: {
        id: number;
        title: string;
    };
    is_overdue?: boolean;
    days_until_due?: number;
}

interface Project {
    id: number;
    title: string;
    project_type: string;
    due_date?: string;
}

interface WeightMetrics {
    total_story_points: number;
    total_tasks_count: number;
    signed_tasks_count: number;
    unsigned_tasks_count: number;
    average_points_per_task: number;
    daily_velocity: number;
    project_breakdown: Record<string, any>;
    size_breakdown: Record<string, any>;
}

interface Stats {
    total_curated_tasks: number;
    pending_tasks: number;
    in_progress_tasks: number;
    completed_tasks: number;
    overdue_tasks: number;
}

interface Props {
    tasks: Task[];
    activeProjects: Project[];
    stats: Stats;
    weightMetrics?: WeightMetrics;
    cache_timestamp: string;
}

const props = defineProps<Props>();

const isRefreshing = ref(false);
const viewMode = ref<'kanban' | 'list'>('kanban');

// Kanban drag and drop state
const kanbanDragOverColumn = ref<string | null>(null);
const draggedTask = ref<Task | null>(null);

// Computed properties for task organization
const tasks = computed(() => props.tasks);

const pendingTasks = computed(() => tasks.value.filter(task => task.status === 'pending'));
const inProgressTasks = computed(() => tasks.value.filter(task => task.status === 'in_progress'));
const completedTasks = computed(() => tasks.value.filter(task => task.status === 'completed'));

// Kanban column heights for consistent layout
const columnHeights = ref({
    pending: 'auto',
    in_progress: 'auto',
    completed: 'auto'
});

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Today\'s Tasks', href: '/dashboard/todays-tasks' }
];

// Task status update functions
const updateTaskStatus = async (taskId: number, newStatus: string) => {
    try {
        const response = await fetch(`/dashboard/todays-tasks/tasks/${taskId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ status: newStatus }),
        });

        if (response.ok) {
            // Reload the page to get updated data
            router.reload({ only: ['tasks', 'stats'] });
        } else {
            console.error('Failed to update task status');
        }
    } catch (error) {
        console.error('Error updating task status:', error);
    }
};

const completeTask = async (taskId: number) => {
    await updateTaskStatus(taskId, 'completed');
};

// Refresh function
const refreshCurations = async () => {
    isRefreshing.value = true;

    try {
        const timestamp = Date.now();
        const response = await fetch(`/dashboard/todays-tasks/refresh?t=${timestamp}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            router.reload({ only: ['tasks', 'stats'] });
        } else {
            alert('Failed to refresh tasks. Please try again.');
        }
    } catch (error) {
        console.error('Error refreshing tasks:', error);
        alert('An error occurred while refreshing tasks.');
    } finally {
        isRefreshing.value = false;
    }
};

// Kanban drag and drop handlers
const onKanbanDragStart = (event: DragEvent, task: Task) => {
    draggedTask.value = task;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/html', '');
    }
};

const onKanbanDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onKanbanDragEnter = (event: DragEvent, status: string) => {
    event.preventDefault();
    kanbanDragOverColumn.value = status;
};

const onKanbanDragLeave = (event: DragEvent) => {
    // Only clear if we're actually leaving the drop zone
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX;
    const y = event.clientY;

    if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
        kanbanDragOverColumn.value = null;
    }
};

const onKanbanDrop = async (event: DragEvent, newStatus: string) => {
    event.preventDefault();
    kanbanDragOverColumn.value = null;

    if (draggedTask.value && draggedTask.value.status !== newStatus) {
        await updateTaskStatus(draggedTask.value.id, newStatus);
    }

    draggedTask.value = null;
};

// Utility functions
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getTaskSizeColor = (size?: string) => {
    const colors = {
        xs: 'bg-green-100 text-green-800',
        s: 'bg-blue-100 text-blue-800',
        m: 'bg-yellow-100 text-yellow-800',
        l: 'bg-orange-100 text-orange-800',
        xl: 'bg-red-100 text-red-800',
    };
    return colors[size as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};

const getTaskSizeLabel = (size?: string) => {
    const labels = {
        xs: 'XS',
        s: 'S',
        m: 'M',
        l: 'L',
        xl: 'XL',
    };
    return labels[size as keyof typeof labels] || '?';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Today's Tasks" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Today's Tasks</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Your curated tasks for today, organized by status
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button
                                @click="refreshCurations"
                                :disabled="isRefreshing"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                <svg v-if="isRefreshing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isRefreshing ? 'Generating...' : 'Generate Today\'s Tasks' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <Card class="rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                        <CardContent class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <CheckSquare class="h-8 w-8 text-blue-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Tasks</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_curated_tasks }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                        <CardContent class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <Clock class="h-8 w-8 text-yellow-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">In Progress</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.in_progress_tasks }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                        <CardContent class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <CheckCircle class="h-8 w-8 text-green-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Completed</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.completed_tasks }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                        <CardContent class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <AlertTriangle class="h-8 w-8 text-red-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Overdue</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.overdue_tasks }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Kanban Board -->
                <div v-if="tasks.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- To Do Column -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <Circle class="h-4 w-4 text-gray-500" />
                            <h3 class="font-medium text-gray-900">To Do</h3>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
                                {{ pendingTasks.length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="pending"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'pending')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'pending')"
                            :class="{ 'bg-blue-100 border-2 border-blue-300 border-dashed rounded-lg': kanbanDragOverColumn === 'pending' }"
                        >
                            <div
                                v-for="task in pendingTasks"
                                :key="task.id"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                class="bg-white p-4 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-medium text-gray-900 text-sm">{{ task.title }}</h4>
                                    <button
                                        @click="updateTaskStatus(task.id, 'in_progress')"
                                        class="text-gray-400 hover:text-blue-600 transition-colors"
                                        title="Start task"
                                    >
                                        <Clock class="h-4 w-4" />
                                    </button>
                                </div>
                                <p v-if="task.description" class="text-xs text-gray-600 mb-2">{{ task.description }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">{{ task.project.title }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="task.size" :class="getTaskSizeColor(task.size)" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium">
                                            {{ getTaskSizeLabel(task.size) }}
                                        </span>
                                        <button
                                            @click="completeTask(task.id)"
                                            class="text-gray-400 hover:text-green-600 transition-colors"
                                            title="Complete task"
                                        >
                                            <CheckCircle class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <Clock class="h-4 w-4 text-blue-600" />
                            <h3 class="font-medium text-gray-900">In Progress</h3>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                                {{ inProgressTasks.length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="in_progress"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'in_progress')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'in_progress')"
                            :class="{ 'bg-blue-100 border-2 border-blue-300 border-dashed rounded-lg': kanbanDragOverColumn === 'in_progress' }"
                        >
                            <div
                                v-for="task in inProgressTasks"
                                :key="task.id"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                class="bg-white p-4 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-medium text-gray-900 text-sm">{{ task.title }}</h4>
                                    <button
                                        @click="updateTaskStatus(task.id, 'pending')"
                                        class="text-gray-400 hover:text-gray-600 transition-colors"
                                        title="Move back to pending"
                                    >
                                        <Circle class="h-4 w-4" />
                                    </button>
                                </div>
                                <p v-if="task.description" class="text-xs text-gray-600 mb-2">{{ task.description }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">{{ task.project.title }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="task.size" :class="getTaskSizeColor(task.size)" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium">
                                            {{ getTaskSizeLabel(task.size) }}
                                        </span>
                                        <button
                                            @click="completeTask(task.id)"
                                            class="text-gray-400 hover:text-green-600 transition-colors"
                                            title="Complete task"
                                        >
                                            <CheckCircle class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Done Column -->
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <CheckCircle class="h-4 w-4 text-green-600" />
                            <h3 class="font-medium text-gray-900">Done</h3>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                {{ completedTasks.length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="completed"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'completed')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'completed')"
                            :class="{ 'bg-blue-100 border-2 border-blue-300 border-dashed rounded-lg': kanbanDragOverColumn === 'completed' }"
                        >
                            <div
                                v-for="task in completedTasks"
                                :key="task.id"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                class="bg-white p-4 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move opacity-75"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-medium text-gray-900 text-sm line-through">{{ task.title }}</h4>
                                    <button
                                        @click="updateTaskStatus(task.id, 'in_progress')"
                                        class="text-gray-400 hover:text-blue-600 transition-colors"
                                        title="Reopen task"
                                    >
                                        <Clock class="h-4 w-4" />
                                    </button>
                                </div>
                                <p v-if="task.description" class="text-xs text-gray-600 mb-2 line-through">{{ task.description }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">{{ task.project.title }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="task.size" :class="getTaskSizeColor(task.size)" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium">
                                            {{ getTaskSizeLabel(task.size) }}
                                        </span>
                                        <CheckCircle class="h-4 w-4 text-green-600" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex items-center justify-center min-h-[400px]">
                    <div class="text-center max-w-md mx-auto">
                        <div class="mb-6">
                            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks curated yet</h3>
                            <p class="text-gray-500 mb-6">
                                Generate today's tasks to get AI-curated suggestions for your projects.
                            </p>
                            <ul class="text-sm text-gray-500 mb-6 space-y-1">
                                <li>• Daily curation hasn't run yet (runs at 3:00 AM)</li>
                                <li>• All your tasks are up to date!</li>
                            </ul>
                        </div>
                        <button
                            @click="refreshCurations"
                            :disabled="isRefreshing"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            {{ isRefreshing ? 'Generating...' : 'Generate Today\'s Tasks' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
