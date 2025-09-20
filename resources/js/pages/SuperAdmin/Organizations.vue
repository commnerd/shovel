<template>
    <Head title="Manage Organizations - Super Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/super-admin" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Super Admin
                        </Link>
                    </Button>
                    <Separator orientation="vertical" class="h-6" />
                    <div>
                        <Heading title="Manage Organizations" class="mb-1 flex items-center gap-2" />
                        <p class="text-sm text-gray-600">System-wide organization management and oversight</p>
                    </div>
                </div>
            </div>

            <!-- Organizations Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <Card
                    v-for="org in organizations.data"
                    :key="org.id"
                    class="hover:shadow-md transition-shadow"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building class="h-5 w-5 text-green-600" />
                            {{ org.name }}
                        </CardTitle>
                        <CardDescription class="line-clamp-2">
                            {{ org.address || 'No address provided' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <!-- Organization Info -->
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Domain:</span>
                                    <span class="font-medium">{{ org.domain_suffix || 'None' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Creator:</span>
                                    <span class="font-medium">{{ org.creator_name || 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Created:</span>
                                    <span class="font-medium">{{ formatDate(org.created_at) }}</span>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ org.users_count }}</div>
                                    <div class="text-xs text-gray-500">Users</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600">{{ org.groups_count }}</div>
                                    <div class="text-xs text-gray-500">Groups</div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 pt-4 border-t">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="viewOrganizationDetails(org)"
                                    class="flex-1"
                                >
                                    View Details
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="manageOrganizationUsers(org)"
                                    class="flex-1"
                                >
                                    Manage Users
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="organizations.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing {{ organizations.from }}-{{ organizations.to }} of {{ organizations.total }} organizations
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-for="page in paginationPages"
                        :key="page"
                        size="sm"
                        :variant="page === organizations.current_page ? 'default' : 'outline'"
                        @click="goToPage(page)"
                        :disabled="page === '...'"
                    >
                        {{ page }}
                    </Button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="organizations.data.length === 0" class="text-center py-12">
                <Building class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Organizations Found</h3>
                <p class="text-gray-500">There are no organizations in the system yet.</p>
            </div>
        </div>

        <!-- Organization Details Modal -->
        <Dialog :open="showDetailsModal" @update:open="showDetailsModal = $event">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Building class="h-5 w-5 text-green-600" />
                        {{ selectedOrganization?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Organization details and management options
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedOrganization" class="space-y-6 py-4">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Organization Name</Label>
                            <p class="text-sm text-gray-700">{{ selectedOrganization.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Domain Suffix</Label>
                            <p class="text-sm text-gray-700">{{ selectedOrganization.domain_suffix || 'None' }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Creator</Label>
                            <p class="text-sm text-gray-700">{{ selectedOrganization.creator_name || 'Unknown' }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Created Date</Label>
                            <p class="text-sm text-gray-700">{{ formatDate(selectedOrganization.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Address</Label>
                        <p class="text-sm text-gray-700">{{ selectedOrganization.address || 'No address provided' }}</p>
                    </div>

                    <!-- Statistics -->
                    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                        <div class="text-center">
                            <div class="text-xl font-bold text-blue-600">{{ selectedOrganization.users_count }}</div>
                            <div class="text-sm text-gray-500">Total Users</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-purple-600">{{ selectedOrganization.groups_count }}</div>
                            <div class="text-sm text-gray-500">Groups</div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showDetailsModal = false">Close</Button>
                    <Button @click="manageOrganizationUsers(selectedOrganization!)">
                        Manage Users
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Building } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface OrganizationData {
    id: number;
    name: string;
    address: string;
    domain_suffix: string;
    creator_id: number;
    creator_name: string;
    users_count: number;
    groups_count: number;
    created_at: string;
}

interface PaginatedOrganizations {
    data: OrganizationData[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    organizations: PaginatedOrganizations;
}

const props = defineProps<Props>();

// Modal state
const showDetailsModal = ref(false);
const selectedOrganization = ref<OrganizationData | null>(null);

// Pagination
const paginationPages = computed(() => {
    const pages = [];
    const current = props.organizations.current_page;
    const last = props.organizations.last_page;

    // Always show first page
    if (current > 3) {
        pages.push(1);
        if (current > 4) pages.push('...');
    }

    // Show pages around current
    for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i);
    }

    // Always show last page
    if (current < last - 2) {
        if (current < last - 3) pages.push('...');
        pages.push(last);
    }

    return pages;
});

// Helper functions
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString();
};

const viewOrganizationDetails = (org: OrganizationData) => {
    selectedOrganization.value = org;
    showDetailsModal.value = true;
};

const manageOrganizationUsers = (org: OrganizationData) => {
    router.get('/super-admin/users', { organization: org.id });
};

const goToPage = (pageNumber: number | string) => {
    if (pageNumber === '...' || pageNumber === props.organizations.current_page) return;

    router.get('/super-admin/organizations', { page: pageNumber }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Super Admin',
        href: '/super-admin',
    },
    {
        title: 'Manage Organizations',
        href: '#',
    },
];
</script>

