<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { KeyRound, Building2, Mail, User } from 'lucide-vue-next';

interface Props {
    token: string;
    email: string;
    organization: {
        name: string;
    } | null;
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(`/invitation/${props.token}`);
};
</script>

<template>
    <Head title="Set Your Password" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-md">
            <div class="flex flex-col gap-8">
                <!-- Logo and Header -->
                <div class="flex flex-col items-center gap-4">
                    <div class="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                        <AppLogoIcon class="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">Welcome!</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            Set your password to complete your account setup
                        </p>
                    </div>
                </div>

                <!-- Invitation Info -->
                <Card class="border-primary/20 bg-primary/5">
                    <CardContent class="pt-6">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-sm">
                                <Mail class="h-4 w-4 text-primary" />
                                <span class="font-medium">{{ email }}</span>
                            </div>
                            <div v-if="organization" class="flex items-center gap-2 text-sm">
                                <Building2 class="h-4 w-4 text-primary" />
                                <span>{{ organization.name }}</span>
                            </div>
                            <div v-else class="flex items-center gap-2 text-sm">
                                <Building2 class="h-4 w-4 text-primary" />
                                <span>Platform Access</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Set Password Form -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <KeyRound class="h-5 w-5" />
                            Set Your Password
                        </CardTitle>
                        <CardDescription>
                            Create your account to get started
                        </CardDescription>
                    </CardHeader>

                    <form @submit.prevent="submit">
                        <CardContent class="space-y-4">
                            <!-- Name -->
                            <div class="space-y-2">
                                <Label for="name">Full Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Enter your full name"
                                    required
                                    :disabled="form.processing"
                                    autofocus
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <!-- Password -->
                            <div class="space-y-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    placeholder="Create a secure password"
                                    required
                                    :disabled="form.processing"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <!-- Confirm Password -->
                            <div class="space-y-2">
                                <Label for="password_confirmation">Confirm Password</Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    placeholder="Confirm your password"
                                    required
                                    :disabled="form.processing"
                                />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <!-- Submit Button -->
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full flex items-center justify-center gap-2"
                            >
                                <User class="h-4 w-4" />
                                {{ form.processing ? 'Creating Account...' : 'Create Account' }}
                            </Button>

                            <!-- Token Error -->
                            <InputError :message="form.errors.token" />
                        </CardContent>
                    </form>
                </Card>

                <!-- Help Text -->
                <div class="text-center text-xs text-muted-foreground">
                    <p>
                        By creating an account, you agree to our terms of service and privacy policy.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
