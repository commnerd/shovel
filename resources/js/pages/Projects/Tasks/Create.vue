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
import { ArrowLeft, Plus, Send } from 'lucide-vue-next';
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
        title: 'Create Task',
        href: `/dashboard/projects/${props.project.id}/tasks/create`,
    },
];

const form = useForm({
    title: '',
    description: '',
    parent_id: '',
    priority: 'medium',
    status: 'pending',
});

const isSubmitting = ref(false);

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

                                <div v-if="parentTasks.length > 0" class="space-y-2">
                                    <Label for="parent_id">Parent Task (Optional)</Label>
                                    <select
                                        id="parent_id"
                                        v-model="form.parent_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="form.processing"
                                    >
                                        <option value="">None (Top-level task)</option>
                                        <option v-for="parentTask in parentTasks" :key="parentTask.id" :value="parentTask.id">
                                            {{ parentTask.title }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.parent_id" />
                                    <p class="text-xs text-gray-500">
                                        Select a parent task to create a subtask
                                    </p>
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
