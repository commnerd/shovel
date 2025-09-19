<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
// Removed unused InputError import
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { LoaderCircle, Building2, Users, ArrowLeft } from 'lucide-vue-next';

interface Organization {
    name: string;
    domain: string;
}

interface Props {
    email: string;
    organization: Organization;
}

defineProps<Props>();

const joinForm = useForm({
    join_organization: true,
});

const declineForm = useForm({
    join_organization: false,
});

const submitJoin = () => {
    joinForm.post('/registration/confirm-organization');
};

const submitDecline = () => {
    declineForm.post('/registration/confirm-organization');
};
</script>

<template>
    <AuthBase title="Organization Detected" description="We found an existing organization for your email domain">
        <Head title="Confirm Organization" />

        <div class="flex flex-col gap-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <Building2 class="h-5 w-5 text-blue-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium text-blue-900 mb-1">Organization Match Found</h3>
                        <div class="space-y-2">
                            <div class="bg-white rounded p-3 border border-blue-200">
                                <p class="font-semibold text-gray-900">{{ organization.name }}</p>
                                <p class="text-sm text-gray-600">@{{ organization.domain }}</p>
                            </div>
                            <p class="text-sm text-blue-700">
                                Your email <strong>{{ email }}</strong> matches the domain for this organization.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center space-y-2">
                <h3 class="font-semibold text-lg">What would you like to do?</h3>
                <p class="text-sm text-gray-600">
                    You can either join the organization or register as an individual user.
                </p>
            </div>

            <div class="grid gap-3">
                <Button
                    @click="submitJoin"
                    class="flex items-center gap-2 h-auto p-4"
                    :disabled="joinForm.processing || declineForm.processing"
                    :tabindex="1"
                >
                    <LoaderCircle v-if="joinForm.processing" class="h-4 w-4 animate-spin" />
                    <Users v-else class="h-5 w-5" />
                    <div class="text-left">
                        <div class="font-medium">Join {{ organization.name }}</div>
                        <div class="text-xs opacity-80">Your registration will need approval from an administrator</div>
                    </div>
                </Button>

                <Button
                    @click="submitDecline"
                    variant="outline"
                    class="flex items-center gap-2 h-auto p-4"
                    :disabled="joinForm.processing || declineForm.processing"
                    :tabindex="2"
                >
                    <LoaderCircle v-if="declineForm.processing" class="h-4 w-4 animate-spin" />
                    <div class="text-left">
                        <div class="font-medium">Register as Individual</div>
                        <div class="text-xs opacity-80">Create a personal account not associated with any organization</div>
                    </div>
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    class="flex items-center gap-2"
                    :tabindex="3"
                    @click="$inertia.visit('/')"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Home
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="login()" class="underline underline-offset-4" :tabindex="4">Log in</TextLink>
            </div>
        </div>
    </AuthBase>
</template>
