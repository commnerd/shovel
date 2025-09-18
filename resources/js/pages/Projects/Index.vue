<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, Folder, Clock, CheckCircle, Calendar } from 'lucide-vue-next';
import CreateProjectForm from './CreateProjectForm.vue';
import type { BreadcrumbItem } from '@/types';

interface Project {
    id: number;
    description: string;
    due_date?: string;
    tasks: Task[];
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

// State for showing create form
const showCreateForm = ref(false);

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
                <Button v-if="hasProjects" class="flex items-center gap-2" @click="showCreateForm = true">
                    <Plus class="h-4 w-4" />
                    New Project
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
                                Project #{{ newProject.id }}
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
                        class="hover:shadow-md transition-shadow cursor-pointer"
                        @click="$inertia.visit(`/dashboard/projects/${project.id}/tasks`)"
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Folder class="h-5 w-5" />
                                Project #{{ project.id }}
                            </CardTitle>
                            <CardDescription>{{ project.description }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div class="text-sm text-gray-500">
                                    {{ project.tasks.length }} tasks
                                </div>
                                <div v-if="project.due_date" class="flex items-center gap-2 text-sm text-gray-600">
                                    <Calendar class="h-4 w-4" />
                                    Due: {{ new Date(project.due_date).toLocaleDateString() }}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Show create form when button is clicked -->
                <div v-if="showCreateForm" class="mt-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold">Create New Project</h3>
                        <Button variant="ghost" size="sm" @click="showCreateForm = false">
                            Cancel
                        </Button>
                    </div>
                    <CreateProjectForm @success="showCreateForm = false" />
                </div>
            </div>

            <!-- Show create form if no projects -->
            <div v-else class="flex items-center justify-center min-h-[400px]">
                <div class="text-center max-w-md mx-auto">
                    <div class="mb-6">
                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <Folder class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No projects yet</h3>
                        <p class="text-gray-500 mb-6">Create your first project to get started with AI-powered task planning.</p>
                    </div>

                    <CreateProjectForm />
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
