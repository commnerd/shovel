<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { Users, FolderOpen } from 'lucide-vue-next';

interface ProjectMetrics {
    total: number;
    active: number;
    completed: number;
    overdue: number;
}

interface TaskMetrics {
    totalLeaf: number;
    completed: number;
    pending: number;
    inProgress: number;
    highPriority: number;
}

const page = usePage();
const waitlistCount = computed(() => page.props.waitlistCount as number);
const projectMetrics = computed(() => page.props.projectMetrics as ProjectMetrics);
const taskMetrics = computed(() => page.props.taskMetrics as TaskMetrics);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-900">
                    <div class="flex flex-col items-center justify-center h-full p-6 text-center">
                        <div class="mb-3 p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                            <Users class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-1">
                            {{ waitlistCount }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Waitlist Subscribers
                        </div>
                    </div>
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-900">
                    <div class="flex flex-col h-full p-4">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900">
                                    <FolderOpen class="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">Projects & Tasks</h3>
                            </div>
                        </div>

                        <!-- Metrics Grid -->
                        <div class="flex-1 grid grid-cols-2 gap-3 text-sm">
                            <!-- Projects Section -->
                            <div class="space-y-2">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wide">Projects</h4>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Total</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ projectMetrics.total }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Active</span>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">{{ projectMetrics.active }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Completed</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ projectMetrics.completed }}</span>
                                    </div>
                                    <div class="flex justify-between" v-if="projectMetrics.overdue > 0">
                                        <span class="text-gray-600 dark:text-gray-400">Overdue</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ projectMetrics.overdue }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Leaf Tasks Section -->
                            <div class="space-y-2">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wide">Leaf Tasks</h4>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Total</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ taskMetrics.totalLeaf }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Completed</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ taskMetrics.completed }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">In Progress</span>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">{{ taskMetrics.inProgress }}</span>
                                    </div>
                                    <div class="flex justify-between" v-if="taskMetrics.highPriority > 0">
                                        <span class="text-gray-600 dark:text-gray-400">High Priority</span>
                                        <span class="font-medium text-orange-600 dark:text-orange-400">{{ taskMetrics.highPriority }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
