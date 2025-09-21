<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Plus,
    Mail,
    Trash2,
    RefreshCw,
    Calendar,
    User,
    Building2,
    AlertCircle,
    CheckCircle,
    Clock
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Organization {
    id: number;
    name: string;
}

interface InvitedBy {
    id: number;
    name: string;
}

interface Invitation {
    id: number;
    email: string;
    organization: Organization | null;
    invited_by: InvitedBy;
    status: 'pending' | 'accepted' | 'expired';
    created_at: string;
    expires_at: string;
    accepted_at: string | null;
}

interface Props {
    invitations: {
        data: Invitation[];
        links: any[];
        meta: any;
    };
    can_invite_users: boolean;
    is_super_admin: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'User Invitations', href: '/admin/invitations' },
];

const isDeleting = ref<number | null>(null);
const isResending = ref<number | null>(null);

const deleteInvitation = (invitationId: number) => {
    if (confirm('Are you sure you want to delete this invitation?')) {
        isDeleting.value = invitationId;
        router.delete(`/admin/invitations/${invitationId}`, {
            onFinish: () => {
                isDeleting.value = null;
            }
        });
    }
};

const resendInvitation = (invitationId: number) => {
    if (confirm('Are you sure you want to resend this invitation?')) {
        isResending.value = invitationId;
        router.post(`/admin/invitations/${invitationId}/resend`, {}, {
            onFinish: () => {
                isResending.value = null;
            }
        });
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'accepted':
            return CheckCircle;
        case 'expired':
            return AlertCircle;
        default:
            return Clock;
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'accepted':
            return 'default';
        case 'expired':
            return 'destructive';
        default:
            return 'secondary';
    }
};
</script>

<template>
    <Head title="User Invitations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Heading title="User Invitations" />
                    <p class="text-sm text-muted-foreground mt-1">
                        Manage user invitations and track their status
                    </p>
                </div>
                <Button v-if="can_invite_users" as-child>
                    <Link href="/admin/invitations/create" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        Invite User
                    </Link>
                </Button>
            </div>

            <!-- Invitations List -->
            <div class="space-y-4">
                <Card v-if="invitations.data.length === 0">
                    <CardContent class="flex flex-col items-center justify-center py-16">
                        <Mail class="h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-lg font-semibold mb-2">No invitations sent</h3>
                        <p class="text-sm text-muted-foreground text-center mb-4">
                            {{ can_invite_users
                                ? 'Start by inviting users to join your organization.'
                                : 'No invitations have been sent yet.'
                            }}
                        </p>
                        <Button v-if="can_invite_users" as-child>
                            <Link href="/admin/invitations/create" class="flex items-center gap-2">
                                <Plus class="h-4 w-4" />
                                Send First Invitation
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card v-for="invitation in invitations.data" :key="invitation.id">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                        <Mail class="h-5 w-5 text-primary" />
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold">{{ invitation.email }}</h3>
                                    <div class="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                                        <div class="flex items-center gap-1">
                                            <Building2 class="h-4 w-4" />
                                            {{ invitation.organization?.name || 'No Organization' }}
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <User class="h-4 w-4" />
                                            Invited by {{ invitation.invited_by.name }}
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Calendar class="h-4 w-4" />
                                            {{ invitation.created_at }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="getStatusVariant(invitation.status)"
                                    class="flex items-center gap-1"
                                >
                                    <component :is="getStatusIcon(invitation.status)" class="h-3 w-3" />
                                    {{ invitation.status.charAt(0).toUpperCase() + invitation.status.slice(1) }}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-muted-foreground">
                                <div v-if="invitation.status === 'accepted' && invitation.accepted_at">
                                    Accepted on {{ invitation.accepted_at }}
                                </div>
                                <div v-else-if="invitation.status === 'expired'">
                                    Expired on {{ invitation.expires_at }}
                                </div>
                                <div v-else>
                                    Expires on {{ invitation.expires_at }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="invitation.status === 'pending'"
                                    variant="outline"
                                    size="sm"
                                    :disabled="isResending === invitation.id"
                                    @click="resendInvitation(invitation.id)"
                                    class="flex items-center gap-2"
                                >
                                    <RefreshCw
                                        class="h-4 w-4"
                                        :class="{ 'animate-spin': isResending === invitation.id }"
                                    />
                                    {{ isResending === invitation.id ? 'Resending...' : 'Resend' }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isDeleting === invitation.id"
                                    @click="deleteInvitation(invitation.id)"
                                    class="flex items-center gap-2 text-destructive hover:text-destructive"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    {{ isDeleting === invitation.id ? 'Deleting...' : 'Delete' }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="invitations.links && invitations.links.length > 3" class="flex justify-center">
                <nav class="flex items-center gap-2">
                    <Link
                        v-for="link in invitations.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 text-sm border rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background hover:bg-muted border-border',
                            !link.url && 'opacity-50 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
