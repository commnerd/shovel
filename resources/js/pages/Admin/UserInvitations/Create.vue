<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
// Using HTML select elements instead of UI components for consistency
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Mail, Send, Building2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Organization {
    id: number;
    name: string;
}

interface Props {
    organizations: Organization[];
    is_super_admin: boolean;
    user_organization: Organization | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'User Invitations', href: '/admin/invitations' },
    { title: 'Invite User', href: '/admin/invitations/create' },
];

const form = useForm({
    email: '',
    organization_id: props.is_super_admin ? null : (props.user_organization?.id || null),
});

const submit = () => {
    form.post('/admin/invitations');
};
</script>

<template>
    <Head title="Invite User" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col justify-center items-center p-4 min-h-[calc(100vh-4rem)]">
            <div class="w-full max-w-2xl space-y-6">
                <!-- Header with back button -->
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/admin/invitations" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Invitations
                        </Link>
                    </Button>
                    <div>
                        <Heading title="Invite User" />
                        <p class="text-sm text-muted-foreground mt-1">
                            Send an invitation to a new user to join the platform
                        </p>
                    </div>
                </div>

                <!-- Invitation Form -->
                <Card>
                    <CardHeader class="text-center">
                        <CardTitle class="flex items-center justify-center gap-2">
                            <Mail class="h-5 w-5 text-primary" />
                            Send User Invitation
                        </CardTitle>
                    </CardHeader>

                    <form @submit.prevent="submit">
                        <CardContent class="space-y-6">
                            <!-- Email -->
                            <div class="space-y-2">
                                <Label for="email">Email Address</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="user@example.com"
                                    required
                                    :disabled="form.processing"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.email" />
                                <p class="text-xs text-muted-foreground">
                                    The user will receive an email with a link to set their password
                                </p>
                            </div>

                            <!-- Organization Selection (Super Admin only) -->
                            <div v-if="is_super_admin" class="space-y-2">
                                <Label for="organization">Organization</Label>
                                <select
                                    id="organization"
                                    v-model="form.organization_id"
                                    :disabled="form.processing"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">No Organization</option>
                                    <option
                                        v-for="org in organizations"
                                        :key="org.id"
                                        :value="org.id"
                                    >
                                        {{ org.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.organization_id" />
                                <p class="text-xs text-muted-foreground">
                                    Leave blank to invite the user without assigning to a specific organization
                                </p>
                            </div>

                            <!-- Organization Info (Admin only) -->
                            <div v-else-if="user_organization" class="space-y-2">
                                <Label>Organization</Label>
                                <div class="flex items-center gap-2 p-3 bg-muted rounded-md">
                                    <Building2 class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">{{ user_organization.name }}</span>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    The user will be invited to join your organization
                                </p>
                            </div>

                            <!-- Domain Restriction Info (Admin only) -->
                            <div v-if="!is_super_admin" class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                <div class="flex gap-3">
                                    <div class="flex-shrink-0">
                                        <Mail class="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-blue-900">Email Domain Restriction</h4>
                                        <p class="text-sm text-blue-700 mt-1">
                                            You can only invite users with email addresses from your organization's domain.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex gap-3">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 flex items-center justify-center gap-2"
                                >
                                    <Send class="h-4 w-4" />
                                    {{ form.processing ? 'Sending Invitation...' : 'Send Invitation' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    as-child
                                    :disabled="form.processing"
                                >
                                    <Link href="/admin/invitations">Cancel</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </form>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
