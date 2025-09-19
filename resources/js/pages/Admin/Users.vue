<template>
    <Head title="Manage Users - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/dashboard" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Dashboard
                        </Link>
                    </Button>
                    <Separator orientation="vertical" class="h-6" />
                    <div>
                        <Heading class="mb-1 flex items-center gap-2">
                            <Shield class="h-6 w-6 text-blue-600" />
                            User Administration
                        </Heading>
                        <p class="text-sm text-gray-600">Manage users within your organization</p>
                    </div>
                </div>
            </div>

            <!-- User Search Interface -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Search class="h-5 w-5 text-blue-600" />
                        Find User to Administer
                    </CardTitle>
                    <CardDescription>
                        Search for users within your organization by name or email address. Select a user to perform administrative actions.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Search Input -->
                        <div class="max-w-md">
                            <UserSearchInput
                                ref="userSearchRef"
                                placeholder="Search users in your organization..."
                                @select="selectUser"
                                @search="handleSearch"
                                :search-url="'/admin/users/search'"
                            />
                        </div>

                        <!-- Search Instructions -->
                        <div v-if="!selectedUser && !hasSearched" class="text-sm text-gray-500">
                            <div class="flex items-start gap-2">
                                <Info class="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                                <div>
                                    <p class="font-medium text-gray-700">Organization Admin Access:</p>
                                    <ul class="mt-1 space-y-1 text-gray-600">
                                        <li>• Search users within your organization only</li>
                                        <li>• Login as users to provide support and troubleshooting</li>
                                        <li>• Access all projects and tasks within your organization</li>
                                        <li>• All actions are logged for audit purposes</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Selected User Details -->
            <Card v-if="selectedUser">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="text-lg font-semibold text-blue-600">{{ selectedUser.avatar }}</span>
                            </div>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    {{ selectedUser.name }}
                                    <Shield v-if="selectedUser.is_admin" class="h-5 w-5 text-blue-500" title="Admin" />
                                </CardTitle>
                                <CardDescription>{{ selectedUser.email }}</CardDescription>
                            </div>
                        </div>
                        <Button variant="ghost" size="sm" @click="clearSelection" class="flex items-center gap-2">
                            <X class="h-4 w-4" />
                            Clear Selection
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- User Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-gray-700">Organization</Label>
                                <p class="text-sm text-gray-900">{{ selectedUser.organization_name }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-gray-700">Status</Label>
                                <div class="flex items-center gap-2">
                                    <Badge v-if="selectedUser.is_admin" variant="secondary" class="bg-blue-100 text-blue-800 border-blue-300">
                                        Admin
                                    </Badge>
                                    <Badge v-else variant="outline" class="bg-gray-50 text-gray-700 border-gray-300">
                                        User
                                    </Badge>

                                    <Badge v-if="selectedUser.pending_approval" variant="outline" class="bg-orange-50 text-orange-700 border-orange-300">
                                        Pending Approval
                                    </Badge>
                                    <Badge v-else variant="outline" class="bg-green-50 text-green-700 border-green-300">
                                        Active
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- Administrative Actions -->
                        <div class="border-t pt-6">
                            <Label class="text-sm font-medium text-gray-700 mb-3 block">Administrative Actions</Label>
                            <div class="flex flex-wrap gap-3">
                                <!-- Login As User -->
                                <Button
                                    variant="outline"
                                    @click="openLoginAsModal(selectedUser)"
                                    :disabled="selectedUser.id === currentUserId"
                                    class="flex items-center gap-2"
                                >
                                    <LogIn class="h-4 w-4" />
                                    Login as {{ selectedUser.name }}
                                </Button>

                                <!-- Note about access -->
                                <div class="w-full mt-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-start gap-2">
                                        <Info class="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                        <div class="text-sm text-blue-800">
                                            <p class="font-medium">Admin Impersonation Access:</p>
                                            <p class="mt-1">When logged in as this user, you'll have access to all their projects, tasks, and subtasks within your organization.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Browse All Users Option -->
            <Card v-if="!selectedUser && hasSearched">
                <CardContent class="pt-6">
                    <div class="text-center py-8">
                        <Users class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No User Selected</h3>
                        <p class="text-gray-500 mb-4">Search for a user above to view their details and perform administrative actions.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Login As User Modal -->
        <Dialog :open="showLoginAsModal" @update:open="showLoginAsModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <LogIn class="h-5 w-5 text-blue-600" />
                        Login as {{ selectedUser?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        You will be logged in as this user within your organization. You can return to your admin account at any time.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="login-reason">Reason (Optional)</Label>
                        <textarea
                            id="login-reason"
                            v-model="loginReason"
                            placeholder="e.g., User support, troubleshooting, training assistance, etc."
                            class="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showLoginAsModal = false">Cancel</Button>
                    <Button @click="loginAsUser" :disabled="isLoggingIn">
                        {{ isLoggingIn ? 'Logging in...' : 'Login as User' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import UserSearchInput from '@/components/UserSearchInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Users, Shield, LogIn, Search, Info, X } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface UserData {
    id: number;
    name: string;
    email: string;
    organization_name: string;
    organization_id: number;
    is_admin: boolean;
    is_super_admin: boolean;
    pending_approval: boolean;
    avatar: string;
}

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

// Search state
const userSearchRef = ref();
const selectedUser = ref<UserData | null>(null);
const hasSearched = ref(false);

// Modal state
const showLoginAsModal = ref(false);

// Form state
const loginReason = ref('');
const isLoggingIn = ref(false);

// Search functions
const selectUser = (user: UserData) => {
    selectedUser.value = user;
    hasSearched.value = true;
};

const handleSearch = (query: string) => {
    if (query.length >= 2) {
        hasSearched.value = true;
    }
};

const clearSelection = () => {
    selectedUser.value = null;
    userSearchRef.value?.clearSearch();
};

// Modal functions
const openLoginAsModal = () => {
    loginReason.value = '';
    showLoginAsModal.value = true;
};

// Actions
const loginAsUser = () => {
    if (!selectedUser.value) return;

    isLoggingIn.value = true;

    router.post(`/admin/users/${selectedUser.value.id}/login-as`, {
        reason: loginReason.value,
    }, {
        onFinish: () => {
            isLoggingIn.value = false;
            showLoginAsModal.value = false;
        },
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'User Administration',
        href: '#',
    },
];
</script>

