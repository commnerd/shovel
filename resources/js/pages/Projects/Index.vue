<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, Folder, Clock, CheckCircle, Calendar, Edit, Eye } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Project {
    id: number;
    title?: string;
    description: string;
    due_date?: string;
    tasks_count: number;
    created_at: string;
}

interface Task {
    id: number;
    title: string;
    description: string;
    status: 'pending' | 'in_progress' | 'completed';
    priority: 'low' | 'medium' | 'high';
}

const page = usePage();
const projects = computed(() => page.props.projects as Project[]);
const newProject = computed(() => page.props.flash?.project as Project | undefined);

const hasProjects = computed(() => projects.value.length > 0 || newProject.value);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
    },
];

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'text-red-600 bg-red-50 border-red-200';
        case 'medium': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
        case 'low': return 'text-green-600 bg-green-50 border-green-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle;
        case 'in_progress': return Clock;
        default: return Folder;
    }
};
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading>Projects</Heading>
                <Button v-if="hasProjects" class="flex items-center gap-2" as-child>
                    <Link href="/dashboard/projects/create">
                        <Plus class="h-4 w-4" />
                        New Project
                    </Link>
                </Button>
            </div>

            <!-- Show projects if they exist -->
            <div v-if="hasProjects" class="space-y-6">
                <!-- Show newly created project first -->
                <div v-if="newProject" class="space-y-4">
                    <h3 class="text-lg font-semibold text-green-700">✨ New Project Created!</h3>
                    <Card class="border-green-200 bg-green-50/50">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Folder class="h-5 w-5 text-green-600" />
                                <span>{{ newProject.title || 'Untitled Project' }}</span>
                                <span v-if="!newProject.title" class="text-xs text-gray-400 font-normal">#{{ newProject.id }}</span>
                            </CardTitle>
                            <CardDescription>{{ newProject.description }}</CardDescription>
                            <div v-if="newProject.due_date" class="flex items-center gap-2 text-sm text-gray-600 mt-2">
                                <Calendar class="h-4 w-4" />
                                Due: {{ new Date(newProject.due_date).toLocaleDateString() }}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <h4 class="font-medium text-sm text-gray-900">AI-Generated Task Layout:</h4>
                                <div class="grid gap-2">
                                    <div
                                        v-for="task in newProject.tasks"
                                        :key="task.id"
                                        class="flex items-center gap-3 p-3 rounded-lg border"
                                        :class="getPriorityColor(task.priority)"
                                    >
                                        <component :is="getStatusIcon(task.status)" class="h-4 w-4 flex-shrink-0" />
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-sm">{{ task.title }}</p>
                                            <p class="text-xs opacity-80">{{ task.description }}</p>
                                        </div>
                                        <span class="text-xs font-medium px-2 py-1 rounded-full bg-white/50">
                                            {{ task.priority }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Existing projects list -->
                <div v-if="projects.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="project in projects"
                        :key="project.id"
                        class="hover:shadow-md transition-shadow"
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Folder class="h-5 w-5" />
                                <span>{{ project.title || 'Untitled Project' }}</span>
                                <span v-if="!project.title" class="text-xs text-gray-400 font-normal">#{{ project.id }}</span>
                            </CardTitle>
                            <CardDescription>{{ project.description }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div class="space-y-2">
                                    <div class="text-sm text-gray-500">
                                        {{ project.tasks_count }} tasks
                                    </div>
                                    <div v-if="project.due_date" class="flex items-center gap-2 text-sm text-gray-600">
                                        <Calendar class="h-4 w-4" />
                                        Due: {{ new Date(project.due_date).toLocaleDateString() }}
                                    </div>
                                </div>

                                <!-- Action buttons -->
                                <div class="flex gap-2 pt-2">
                                    <Button size="sm" variant="outline" as-child class="flex-1">
                                        <Link :href="`/dashboard/projects/${project.id}/tasks`" class="flex items-center gap-2">
                                            <Eye class="h-4 w-4" />
                                            View Tasks
                                        </Link>
                                    </Button>
                                    <Button size="sm" variant="ghost" as-child>
                                        <Link :href="`/dashboard/projects/${project.id}/edit`" class="flex items-center gap-2">
                                            <Edit class="h-4 w-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>
            </div>
        </div>
    </AppLayout>
</template>
