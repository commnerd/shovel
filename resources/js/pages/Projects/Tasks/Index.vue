<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Filter, CheckCircle, Clock, Circle, Calendar, Users, Layers, Plus, Edit, Trash2, TreePine, Leaf, GitBranch, Sparkles } from 'lucide-vue-next';
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

// No longer needed - AI breakdown is now on dedicated page

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
            <div v-if="hasTasks" class="space-y-2">
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    class="flex items-center gap-3 p-3 rounded-lg border bg-white hover:shadow-md transition-shadow"
                    :class="{ 'ml-6': task.depth > 0 && currentFilter !== 'leaf' }"
                >
                    <!-- Status and type icons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <component
                            :is="getStatusIcon(task.status)"
                            class="h-4 w-4"
                            :class="getStatusColor(task.status).split(' ')[0]"
                        />
                        <component
                            :is="getTaskTypeIcon(task)"
                            class="h-3 w-3 text-gray-400"
                        />
                    </div>

                    <!-- Task title -->
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-gray-900 truncate block">{{ task.title }}</span>
                    </div>

                    <!-- Priority badge - hidden on small screens -->
                    <span
                        :class="getPriorityColor(task.priority)"
                        class="hidden sm:inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                    >
                        {{ task.priority }}
                    </span>

                    <!-- Status badge - hidden on small screens -->
                    <span
                        :class="getStatusColor(task.status)"
                        class="hidden sm:inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                    >
                        {{ task.status.replace('_', ' ') }}
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <!-- Subtasks group -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium">Subtasks:</span>
                            <div class="flex items-center border rounded-md">
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-r-none border-r">
                                    <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" class="flex items-center gap-1">
                                        <Plus class="h-3 w-3" />
                                        <span class="text-xs hidden sm:inline">Add</span>
                                    </Link>
                                </Button>
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-l-none">
                                    <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/breakdown`" class="flex items-center gap-1">
                                        <Sparkles class="h-3 w-3" />
                                        <span class="text-xs hidden sm:inline">Generate</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Edit and Delete buttons -->
                        <div class="flex items-center gap-1">
                            <Button size="sm" variant="ghost" as-child class="h-8 w-8 p-0">
                                <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`">
                                    <Edit class="h-3 w-3" />
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                @click="deleteTask(task.id)"
                            >
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
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
