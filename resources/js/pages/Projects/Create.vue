<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import CreateProjectForm from './CreateProjectForm.vue';
import type { BreadcrumbItem } from '@/types';

interface Group {
    id: number;
    name: string;
    description?: string;
    is_default: boolean;
    organization_name: string;
}

interface Props {
    userGroups: Group[];
    defaultGroupId?: number;
}

defineProps<Props>();

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
];
</script>

<template>
    <Head title="Create Project" />

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
                        <Heading title="Create New Project" />
                        <p class="text-sm text-gray-600 mt-1">
                            Describe your project and let AI create an initial task layout
                        </p>
                    </div>
                </div>

                <!-- Create form -->
                <div class="w-full">
                    <CreateProjectForm
                        :user-groups="userGroups"
                        :default-group-id="defaultGroupId"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
