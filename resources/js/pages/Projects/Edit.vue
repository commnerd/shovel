<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Edit, Save, Calendar, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Project {
    id: number;
    title?: string;
    description: string;
    due_date?: string;
    status: string;
}

const props = defineProps<{
    project: Project;
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
        title: `Edit ${props.project.title || 'Untitled Project'}`,
        href: `/dashboard/projects/${props.project.id}/edit`,
    },
];

const form = useForm({
    title: props.project.title || '',
    description: props.project.description,
    due_date: props.project.due_date || '',
    status: props.project.status,
});

const isSubmitting = ref(false);

const submit = () => {
    if (form.processing) return;

    isSubmitting.value = true;

    form.put(`/dashboard/projects/${props.project.id}`, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        submit();
    }
};

const deleteProject = () => {
    if (confirm('Are you sure you want to delete this project? This will also delete all tasks. This action cannot be undone.')) {
        console.log(`Attempting to delete project ${props.project.id}`);

        router.delete(`/dashboard/projects/${props.project.id}`, {
            onStart: () => {
                console.log('Delete request started');
            },
            onSuccess: (page) => {
                console.log('Project deleted successfully', page);
            },
            onError: (errors) => {
                console.error('Failed to delete project:', errors);

                // Show specific error message if available
                const errorMessage = errors.error || errors.message || 'Failed to delete project. Please try again.';
                alert(errorMessage);
            },
            onFinish: () => {
                console.log('Delete request completed');
            }
        });
    }
};
</script>

<template>
    <Head :title="`Edit ${project.title || 'Untitled Project'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col justify-center items-center p-4 min-h-[calc(100vh-4rem)]">
            <div class="w-full max-w-2xl space-y-6">
                <!-- Header with back button -->
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/dashboard/projects" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Projects
                        </Link>
                    </Button>
                    <div>
                        <Heading :title="`Edit ${project.title || 'Untitled Project'}`" />
                        <p class="text-sm text-gray-600 mt-1">
                            Update your project details and settings
                        </p>
                    </div>
                </div>

                <!-- Edit form -->
                <div class="max-w-2xl">
                    <Card>
                        <CardHeader class="text-center">
                            <CardTitle class="flex items-center justify-center gap-2">
                                <Edit class="h-5 w-5 text-blue-600" />
                                Edit Project
                            </CardTitle>
                            <CardDescription>
                                Make changes to your project details
                            </CardDescription>
                        </CardHeader>

                        <form @submit.prevent="submit">
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title">Project Title</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        placeholder="e.g., Peanut Butter and Jelly Sandwich"
                                        :disabled="form.processing"
                                        class="w-full"
                                    />
                                    <InputError :message="form.errors.title" />
                                    <p class="text-xs text-gray-500">
                                        A descriptive name for your project
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Project Description</Label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="e.g., Make a peanut butter and jelly sandwich..."
                                        class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                        :disabled="form.processing"
                                        @keydown="handleKeydown"
                                        required
                                    ></textarea>
                                    <InputError :message="form.errors.description" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="due_date" class="flex items-center gap-2">
                                        <Calendar class="h-4 w-4" />
                                        Due Date (Optional)
                                    </Label>
                                    <Input
                                        id="due_date"
                                        v-model="form.due_date"
                                        type="date"
                                        :disabled="form.processing"
                                        class="w-full"
                                    />
                                    <InputError :message="form.errors.due_date" />
                                    <p class="text-xs text-gray-500">
                                        Leave empty if no specific deadline
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="status">Project Status</Label>
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="form.processing"
                                    >
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                    <InputError :message="form.errors.status" />
                                </div>

                                <div class="text-xs text-gray-500 pt-2">
                                    Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 rounded">⌘ + Enter</kbd> to save
                                </div>
                            </CardContent>

                            <CardFooter class="flex gap-3">
                                <Button
                                    type="submit"
                                    class="flex-1 flex items-center gap-2"
                                    :disabled="form.processing || !form.description.trim()"
                                >
                                    <Save v-if="!isSubmitting" class="h-4 w-4" />
                                    <div v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link href="/dashboard/projects">Cancel</Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="deleteProject"
                                    :disabled="form.processing"
                                    class="flex items-center gap-2"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Delete
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
