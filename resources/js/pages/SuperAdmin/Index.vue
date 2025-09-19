<template>
    <Head title="Super Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Heading class="mb-1 flex items-center gap-2">
                        <Crown class="h-6 w-6 text-yellow-600" />
                        Super Admin Dashboard
                    </Heading>
                    <p class="text-sm text-gray-600">System-wide administration and user management</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                        <Users class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_users }}</div>
                        <p class="text-xs text-muted-foreground">Across all organizations</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Organizations</CardTitle>
                        <Building class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_organizations }}</div>
                        <p class="text-xs text-muted-foreground">Active organizations</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pending Approvals</CardTitle>
                        <Clock class="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.pending_users }}</div>
                        <p class="text-xs text-muted-foreground">Users awaiting approval</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Super Admins</CardTitle>
                        <Crown class="h-4 w-4 text-yellow-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.super_admins }}</div>
                        <p class="text-xs text-muted-foreground">System administrators</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5 text-blue-600" />
                            User Management
                        </CardTitle>
                        <CardDescription>
                            Manage users across all organizations
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600">
                                View, edit, and manage all users in the system. Assign super admin roles and login as other users for support.
                            </p>
                            <Button as-child class="w-full">
                                <Link href="/super-admin/users">
                                    Manage Users
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building class="h-5 w-5 text-green-600" />
                            Organization Management
                        </CardTitle>
                        <CardDescription>
                            Manage organizations and their settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600">
                                View and manage all organizations, their groups, and administrative settings.
                            </p>
                            <Button as-child class="w-full">
                                <Link href="/super-admin/organizations">
                                    Manage Organizations
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Crown, Users, Building, Clock } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Stats {
    total_users: number;
    total_organizations: number;
    pending_users: number;
    super_admins: number;
}

interface Props {
    stats: Stats;
}

defineProps<Props>();


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Super Admin',
        href: '#',
    },
];
</script>
