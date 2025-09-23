<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
// Removed Wayfinder import - using direct route names
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { Users, FolderOpen, Bot, TrendingUp, AlertTriangle, CheckCircle } from 'lucide-vue-next';

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
}

interface AIUsageMetrics {
    api_usage: {
        total_requests: number;
        total_tokens: number;
        total_cost: number;
        date?: string;
        period?: string;
    } | null;
    local_usage: {
        today: {
            requests: number;
            successful_requests: number;
            failed_requests: number;
            tokens_estimated: number;
            cost_estimated: number;
        };
        month: {
            requests: number;
            tokens_estimated: number;
            cost_estimated: number;
        };
        recent_requests: number;
    };
    quota_info: {
        hard_limit_usd: number;
        soft_limit_usd: number;
        has_payment_method: boolean;
    } | null;
    status: string;
    error?: string;
    last_updated: string;
}

const page = usePage();
const waitlistCount = computed(() => page.props.waitlistCount as number);
const projectMetrics = computed(() => page.props.projectMetrics as ProjectMetrics);
const taskMetrics = computed(() => page.props.taskMetrics as TaskMetrics);
const aiUsageMetrics = computed(() => page.props.aiUsageMetrics as AIUsageMetrics | null);

// Check if user is super admin
const isSuperAdmin = computed(() => {
    return (page.props.auth as any)?.user?.is_super_admin || false;
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- AI Usage Metrics for Super Admins -->
                <div v-if="isSuperAdmin && aiUsageMetrics" class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-900">
                    <div class="flex flex-col h-full p-4">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-lg bg-purple-100 dark:bg-purple-900">
                                    <Bot class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">AI Usage</h3>
                            </div>
                            <div class="flex items-center gap-1">
                                <div v-if="aiUsageMetrics.status === 'success'" class="w-2 h-2 rounded-full bg-green-400"></div>
                                <div v-else class="w-2 h-2 rounded-full bg-red-400"></div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Live</span>
                            </div>
                        </div>

                        <!-- Error State -->
                        <div v-if="aiUsageMetrics.status === 'error'" class="flex-1 flex flex-col items-center justify-center text-center">
                            <AlertTriangle class="h-8 w-8 text-red-500 mb-2" />
                            <p class="text-sm text-red-600 dark:text-red-400 mb-1">Unable to load metrics</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ aiUsageMetrics.error }}</p>
                        </div>

                        <!-- Metrics Grid -->
                        <div v-else class="flex-1 grid grid-cols-2 gap-3 text-sm">
                            <!-- Today's Usage -->
                            <div class="space-y-2">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wide">Today</h4>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Requests</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ aiUsageMetrics.local_usage.today.requests }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Success</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ aiUsageMetrics.local_usage.today.successful_requests }}</span>
                                    </div>
                                    <div class="flex justify-between" v-if="aiUsageMetrics.local_usage.today.failed_requests > 0">
                                        <span class="text-gray-600 dark:text-gray-400">Failed</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ aiUsageMetrics.local_usage.today.failed_requests }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Cost</span>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">${{ aiUsageMetrics.local_usage.today.cost_estimated.toFixed(4) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Usage -->
                            <div class="space-y-2">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wide">This Month</h4>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Requests</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ aiUsageMetrics.local_usage.month.requests }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Tokens</span>
                                        <span class="font-medium text-purple-600 dark:text-purple-400">{{ aiUsageMetrics.local_usage.month.tokens_estimated.toLocaleString() }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Cost</span>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">${{ aiUsageMetrics.local_usage.month.cost_estimated.toFixed(4) }}</span>
                                    </div>
                                    <div v-if="aiUsageMetrics.quota_info?.hard_limit_usd" class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Limit</span>
                                        <span class="font-medium text-orange-600 dark:text-orange-400">${{ aiUsageMetrics.quota_info.hard_limit_usd }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- API Usage Status -->
                        <div v-if="aiUsageMetrics.api_usage" class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">API Data</span>
                                <div class="flex items-center gap-1">
                                    <CheckCircle class="h-3 w-3 text-green-500" />
                                    <span class="text-green-600 dark:text-green-400">Live</span>
                                </div>
                            </div>
                        </div>

                        <!-- Last Updated -->
                        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                Updated {{ new Date(aiUsageMetrics.last_updated).toLocaleTimeString() }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder for non-super admins -->
                <div v-else class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
