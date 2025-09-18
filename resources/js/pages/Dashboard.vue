<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { Users } from 'lucide-vue-next';

const page = usePage();
const waitlistCount = computed(() => page.props.waitlistCount as number);

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
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
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
