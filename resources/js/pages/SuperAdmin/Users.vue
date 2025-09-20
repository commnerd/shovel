<template>
    <Head title="Manage Users - Super Admin" />

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
                        <Heading title="User Administration" class="mb-1 flex items-center gap-2" />
                        <p class="text-sm text-gray-600">Search and manage users across all organizations</p>
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
                        Search for users by name or email address. Select a user to view their details and perform administrative actions.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Search Input -->
                        <div class="max-w-md">
                            <UserSearchInput
                                ref="userSearchRef"
                                placeholder="Search users by name or email..."
                                @select="selectUser"
                                @search="handleSearch"
                            />
                        </div>

                        <!-- Search Instructions -->
                        <div v-if="!selectedUser && !hasSearched" class="text-sm text-gray-500">
                            <div class="flex items-start gap-2">
                                <Info class="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                                <div>
                                    <p class="font-medium text-gray-700">How to use:</p>
                                    <ul class="mt-1 space-y-1 text-gray-600">
                                        <li>• Type at least 2 characters to start searching</li>
                                        <li>• Search by name (e.g., "John Doe") or email (e.g., "john@")</li>
                                        <li>• Use arrow keys to navigate results, Enter to select</li>
                                        <li>• Results are sorted by relevance (exact matches first)</li>
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
                                    <Crown v-if="selectedUser.is_super_admin" class="h-5 w-5 text-yellow-500" title="Super Admin" />
                                    <Shield v-else-if="selectedUser.is_admin" class="h-5 w-5 text-blue-500" title="Admin" />
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
                                    <Badge v-if="selectedUser.is_super_admin" variant="destructive" class="bg-yellow-100 text-yellow-800 border-yellow-300">
                                        Super Admin
                                    </Badge>
                                    <Badge v-else-if="selectedUser.is_admin" variant="secondary" class="bg-blue-100 text-blue-800 border-blue-300">
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

                                <!-- Super Admin Management -->
                                <Button
                                    v-if="!selectedUser.is_super_admin"
                                    variant="outline"
                                    @click="openAssignSuperAdminModal(selectedUser)"
                                    class="flex items-center gap-2 text-yellow-600 border-yellow-300 hover:bg-yellow-50"
                                >
                                    <Crown class="h-4 w-4" />
                                    Assign Super Admin Role
                                </Button>
                                <Button
                                    v-else-if="selectedUser.id !== currentUserId"
                                    variant="outline"
                                    @click="openRemoveSuperAdminModal(selectedUser)"
                                    class="flex items-center gap-2 text-red-600 border-red-300 hover:bg-red-50"
                                >
                                    <Crown class="h-4 w-4" />
                                    Remove Super Admin Role
                                </Button>

                                <!-- View Full Profile (Future Enhancement) -->
                                <Button
                                    variant="ghost"
                                    class="flex items-center gap-2"
                                    disabled
                                >
                                    <User class="h-4 w-4" />
                                    View Full Profile
                                    <Badge variant="outline" class="ml-1 text-xs">Coming Soon</Badge>
                                </Button>
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
                        <Button variant="outline" as-child>
                            <Link href="/super-admin/users?view=all" class="flex items-center gap-2">
                                <List class="h-4 w-4" />
                                Browse All Users (Legacy View)
                            </Link>
                        </Button>
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
                        You will be logged in as this user. You can return to your super admin account at any time.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="login-reason">Reason (Optional)</Label>
                        <textarea
                            id="login-reason"
                            v-model="loginReason"
                            placeholder="e.g., Troubleshooting user issue, Support request, etc."
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

        <!-- Assign Super Admin Modal -->
        <Dialog :open="showAssignSuperAdminModal" @update:open="showAssignSuperAdminModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Crown class="h-5 w-5 text-yellow-600" />
                        Assign Super Admin Role
                    </DialogTitle>
                    <DialogDescription>
                        Grant super admin privileges to {{ selectedUser?.name }}. This action will be logged.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="assign-reason">Reason (Required)</Label>
                        <textarea
                            id="assign-reason"
                            v-model="assignReason"
                            placeholder="e.g., Promoting to system administrator, Replacing departing super admin, etc."
                            class="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            required
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAssignSuperAdminModal = false">Cancel</Button>
                    <Button @click="assignSuperAdmin" :disabled="!assignReason.trim() || isAssigning">
                        {{ isAssigning ? 'Assigning...' : 'Assign Super Admin' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove Super Admin Modal -->
        <Dialog :open="showRemoveSuperAdminModal" @update:open="showRemoveSuperAdminModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Crown class="h-5 w-5 text-red-600" />
                        Remove Super Admin Role
                    </DialogTitle>
                    <DialogDescription>
                        Remove super admin privileges from {{ selectedUser?.name }}. This action will be logged.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="remove-reason">Reason (Required)</Label>
                        <textarea
                            id="remove-reason"
                            v-model="removeReason"
                            placeholder="e.g., Role no longer needed, User leaving organization, etc."
                            class="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            required
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showRemoveSuperAdminModal = false">Cancel</Button>
                    <Button variant="destructive" @click="removeSuperAdmin" :disabled="!removeReason.trim() || isRemoving">
                        {{ isRemoving ? 'Removing...' : 'Remove Super Admin' }}
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
import { ArrowLeft, Users, User, Crown, Shield, LogIn, Search, Info, X, List } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface UserData {
    id: number;
    name: string;
    email: string;
    organization_id: number;
    organization_name: string;
    is_admin: boolean;
    is_super_admin: boolean;
    pending_approval: boolean;
    approved_at: string | null;
    created_at: string;
    groups_count: number;
    roles_count: number;
}

interface PaginatedUsers {
    data: UserData[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    users: PaginatedUsers;
}

defineProps<Props>();
const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

// Search state
const userSearchRef = ref();
const selectedUser = ref<UserData | null>(null);
const hasSearched = ref(false);

// Modal state
const showLoginAsModal = ref(false);
const showAssignSuperAdminModal = ref(false);
const showRemoveSuperAdminModal = ref(false);

// Form state
const loginReason = ref('');
const assignReason = ref('');
const removeReason = ref('');
const isLoggingIn = ref(false);
const isAssigning = ref(false);
const isRemoving = ref(false);

// Removed unused paginationPages computed

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

const openAssignSuperAdminModal = () => {
    assignReason.value = '';
    showAssignSuperAdminModal.value = true;
};

const openRemoveSuperAdminModal = () => {
    removeReason.value = '';
    showRemoveSuperAdminModal.value = true;
};

// Actions
const loginAsUser = () => {
    if (!selectedUser.value) return;

    isLoggingIn.value = true;

    router.post(`/super-admin/users/${selectedUser.value.id}/login-as`, {
        reason: loginReason.value,
    }, {
        onFinish: () => {
            isLoggingIn.value = false;
            showLoginAsModal.value = false;
        },
    });
};

const assignSuperAdmin = () => {
    if (!selectedUser.value || !assignReason.value.trim()) return;

    isAssigning.value = true;

    router.post(`/super-admin/users/${selectedUser.value.id}/assign-super-admin`, {
        reason: assignReason.value,
    }, {
        onFinish: () => {
            isAssigning.value = false;
            showAssignSuperAdminModal.value = false;
        },
    });
};

const removeSuperAdmin = () => {
    if (!selectedUser.value || !removeReason.value.trim()) return;

    isRemoving.value = true;

    router.post(`/super-admin/users/${selectedUser.value.id}/remove-super-admin`, {
        reason: removeReason.value,
    }, {
        onFinish: () => {
            isRemoving.value = false;
            showRemoveSuperAdminModal.value = false;
        },
    });
};

// Removed unused goToPage function

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
        title: 'Manage Users',
        href: '#',
    },
];
</script>
