<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Filter, CheckCircle, Clock, Circle, Calendar, Users, Layers, Plus, Edit, Trash2, TreePine, Leaf, GitBranch, Sparkles, Loader } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    priority: 'low' | 'medium' | 'high';
    parent_id?: number;
    has_children: boolean;
    depth: number;
    is_top_level: boolean;
    is_leaf: boolean;
    created_at: string;
}

interface Project {
    id: number;
    description: string;
    due_date?: string;
    status: string;
}

interface TaskCounts {
    all: number;
    top_level: number;
    leaf: number;
}

const page = usePage();
const project = computed(() => page.props.project as Project);
const tasks = computed(() => page.props.tasks as Task[]);
const currentFilter = computed(() => page.props.filter as string);
const taskCounts = computed(() => page.props.taskCounts as TaskCounts);

const filterOptions = [
    { value: 'all', label: 'All Tasks', icon: Layers, count: taskCounts.value.all },
    { value: 'top-level', label: 'Top-Level', icon: Circle, count: taskCounts.value.top_level },
    { value: 'leaf', label: 'Leaf Tasks', icon: CheckCircle, count: taskCounts.value.leaf },
];

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle;
        case 'in_progress': return Clock;
        default: return Circle;
    }
};

const getTaskTypeIcon = (task: Task) => {
    if (task.is_top_level && task.has_children) return TreePine;
    if (task.has_children) return GitBranch;
    if (task.is_leaf) return Leaf;
    return Circle;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'text-green-600 bg-green-50 border-green-200';
        case 'in_progress': return 'text-blue-600 bg-blue-50 border-blue-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'text-red-600 bg-red-50 border-red-200';
        case 'medium': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
        case 'low': return 'text-green-600 bg-green-50 border-green-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

const changeFilter = (filter: string) => {
    router.get(`/dashboard/projects/${project.value.id}/tasks`, { filter }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Mock some tasks if none exist
const hasTasks = computed(() => tasks.value.length > 0);

const deleteTask = (taskId: number) => {
    if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        router.delete(`/dashboard/projects/${project.value.id}/tasks/${taskId}`, {
            preserveScroll: true,
        });
    }
};

// AI Task Breakdown functionality
const breakdownTaskId = ref<number | null>(null);
const isGeneratingBreakdown = ref(false);
const suggestedSubtasks = ref<any[]>([]);
const aiNotes = ref<string[]>([]);
const showBreakdownResults = ref(false);

const generateAIBreakdown = async (task: Task) => {
    breakdownTaskId.value = task.id;
    isGeneratingBreakdown.value = true;
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    showBreakdownResults.value = false;

    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/breakdown`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: task.title,
                description: task.description || '',
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

const createSubtasksFromBreakdown = async () => {
    if (!breakdownTaskId.value || suggestedSubtasks.value.length === 0) return;

    try {
        // Create each subtask individually
        for (const [index, subtask] of suggestedSubtasks.value.entries()) {
            await fetch(`/dashboard/projects/${project.value.id}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    title: subtask.title,
                    description: subtask.description || '',
                    parent_id: breakdownTaskId.value,
                    priority: subtask.priority,
                    status: subtask.status,
                    due_date: subtask.due_date || null,
                }),
            });
        }

        // Clear breakdown state and refresh page
        clearBreakdown();
        router.reload({ preserveScroll: true });
    } catch (error) {
        console.error('Subtask creation error:', error);
        alert('Failed to create subtasks. Please try again.');
    }
};

const clearBreakdown = () => {
    breakdownTaskId.value = null;
    suggestedSubtasks.value = [];
    aiNotes.value = [];
    showBreakdownResults.value = false;
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
        title: project.value.title || 'Untitled Project',
        href: `/dashboard/projects/${project.value.id}/tasks`,
    },
];
</script>

<template>
    <Head :title="`Tasks - ${project.title || 'Untitled Project'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="space-y-6">
            <!-- Header with back button -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/dashboard/projects" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Projects
                        </Link>
                    </Button>
                    <Separator orientation="vertical" class="h-6" />
                    <div>
                        <Heading class="mb-1">Project Tasks</Heading>
                        <p class="text-sm text-gray-600">{{ project.description }}</p>
                        <div v-if="project.due_date" class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <Calendar class="h-4 w-4" />
                            Due: {{ new Date(project.due_date).toLocaleDateString() }}
                        </div>
                    </div>
                </div>
                <Button class="flex items-center gap-2" as-child>
                    <Link :href="`/dashboard/projects/${project.id}/tasks/create`">
                        <Plus class="h-4 w-4" />
                        New Task
                    </Link>
                </Button>
            </div>

            <!-- Filter tabs -->
            <div class="flex items-center gap-2 border-b">
                <Filter class="h-4 w-4 text-gray-500" />
                <span class="text-sm font-medium text-gray-700 mr-2">Filter:</span>
                <div class="flex gap-1">
                    <Button
                        v-for="option in filterOptions"
                        :key="option.value"
                        :variant="currentFilter === option.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="changeFilter(option.value)"
                        class="flex items-center gap-2"
                    >
                        <component :is="option.icon" class="h-4 w-4" />
                        {{ option.label }}
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">{{ option.count }}</span>
                    </Button>
                </div>
            </div>

            <!-- Tasks list -->
            <div v-if="hasTasks" class="space-y-3">
                <Card
                    v-for="task in tasks"
                    :key="task.id"
                    class="hover:shadow-md transition-shadow"
                    :class="{ 'ml-6': task.depth > 0 }"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="flex items-center gap-3 text-base">
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="getStatusIcon(task.status)"
                                            class="h-5 w-5 flex-shrink-0"
                                            :class="getStatusColor(task.status).split(' ')[0]"
                                        />
                                        <component
                                            :is="getTaskTypeIcon(task)"
                                            class="h-4 w-4 flex-shrink-0 text-gray-400"
                                        />
                                    </div>
                                    <span class="flex-1">{{ task.title }}</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            :class="getPriorityColor(task.priority)"
                                            class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium"
                                        >
                                            {{ task.priority }}
                                        </span>
                                        <span
                                            :class="getStatusColor(task.status)"
                                            class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium"
                                        >
                                            {{ task.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                </CardTitle>
                                <CardDescription v-if="task.description" class="mt-2">
                                    {{ task.description }}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="space-y-3">
                            <div v-if="task.has_children || task.parent_id || task.depth > 0" class="flex items-center gap-4 text-xs text-gray-500">
                                <div v-if="task.has_children" class="flex items-center gap-1">
                                    <Users class="h-3 w-3" />
                                    Has subtasks
                                </div>
                                <div v-if="task.parent_id" class="flex items-center gap-1">
                                    <ArrowLeft class="h-3 w-3" />
                                    Subtask
                                </div>
                                <div v-if="task.depth > 0" class="flex items-center gap-1">
                                    <Layers class="h-3 w-3" />
                                    Depth: {{ task.depth }}
                                </div>
                                <div class="flex items-center gap-1">
                                    <span>{{ task.is_top_level ? 'Top-level' : task.is_leaf ? 'Leaf' : 'Branch' }}</span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex gap-2 pt-2">
                                <Button size="sm" variant="outline" as-child class="flex-1">
                                    <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`" class="flex items-center gap-2">
                                        <Edit class="h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <!-- Split Add Subtask and AI Breakdown buttons -->
                                <div class="flex gap-1 flex-1">
                                    <Button size="sm" variant="outline" as-child class="flex-1">
                                        <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" class="flex items-center gap-2">
                                            <Plus class="h-4 w-4" />
                                            Add Subtask
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="flex-1 flex items-center gap-2"
                                        @click="generateAIBreakdown(task)"
                                        :disabled="isGeneratingBreakdown && breakdownTaskId === task.id"
                                    >
                                        <Loader v-if="isGeneratingBreakdown && breakdownTaskId === task.id" class="h-4 w-4 animate-spin" />
                                        <Sparkles v-else class="h-4 w-4" />
                                        AI Breakdown
                                    </Button>
                                </div>

                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50"
                                    @click="deleteTask(task.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- AI Breakdown Results -->
            <div v-if="showBreakdownResults && suggestedSubtasks.length > 0" class="mt-6">
                <Card class="border-blue-200 bg-blue-50">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-blue-900 flex items-center gap-2">
                                <Sparkles class="h-5 w-5" />
                                AI Generated Subtasks
                            </CardTitle>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="clearBreakdown"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                Clear
                            </Button>
                        </div>
                        <CardDescription class="text-blue-700">
                            AI has suggested {{ suggestedSubtasks.length }} subtasks for this task
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <!-- AI Notes -->
                        <div v-if="aiNotes.length > 0" class="mb-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">AI Analysis:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li v-for="note in aiNotes" :key="note" class="flex items-start gap-2">
                                    <span class="text-blue-400 mt-1">•</span>
                                    <span>{{ note }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Suggested Subtasks -->
                        <div class="space-y-3 mb-4">
                            <h4 class="text-sm font-medium text-blue-800">Suggested Subtasks:</h4>
                            <div class="grid gap-3 max-h-80 overflow-y-auto">
                                <div
                                    v-for="(subtask, index) in suggestedSubtasks"
                                    :key="index"
                                    class="p-3 bg-white border border-blue-100 rounded-md hover:border-blue-200 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <h5 class="text-sm font-medium text-gray-900">{{ subtask.title }}</h5>
                                            <p class="text-sm text-gray-600 mt-1">{{ subtask.description }}</p>
                                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100">
                                                    {{ subtask.priority }}
                                                </span>
                                                <span v-if="subtask.due_date" class="flex items-center gap-1">
                                                    <Calendar class="h-3 w-3" />
                                                    {{ subtask.due_date }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <Button
                                @click="createSubtasksFromBreakdown"
                                class="flex-1"
                            >
                                Create All Subtasks
                            </Button>
                            <Button
                                variant="outline"
                                @click="generateAIBreakdown(tasks.find(t => t.id === breakdownTaskId)!)"
                                :disabled="isGeneratingBreakdown"
                            >
                                Regenerate
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <div v-if="!hasTasks" class="flex items-center justify-center min-h-[400px]">
                <div class="text-center max-w-md mx-auto">
                    <div class="mb-6">
                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <CheckCircle class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No tasks yet</h3>
                        <p class="text-gray-500 mb-6">
                            This project doesn't have any tasks yet.
                            <span v-if="currentFilter !== 'all'">
                                Try changing the filter or create some tasks to get started.
                            </span>
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Button @click="changeFilter('all')" variant="outline" class="w-full">
                            View All Tasks
                        </Button>
                        <Button variant="default" class="w-full" as-child>
                            <Link :href="`/dashboard/projects/${project.id}/tasks/create`">
                                Create First Task
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
