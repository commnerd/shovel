<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
// import { Textarea } from '@/components/ui/textarea';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { LoaderCircle, Building2, ArrowLeft } from 'lucide-vue-next';

interface Props {
    email: string;
}

defineProps<Props>();

const form = useForm({
    organization_name: '',
    organization_address: '',
});

const submit = () => {
    form.post('/organization/create');
};
</script>

<template>
    <AuthBase title="Create Your Organization" description="Set up your organization to get started">
        <Head title="Create Organization" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="flex items-center gap-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <Building2 class="h-5 w-5 text-blue-600" />
                    <div>
                        <p class="text-sm font-medium text-blue-900">Organization Email Detected</p>
                        <p class="text-xs text-blue-700">{{ email }}</p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="organization_name">Organization Name</Label>
                    <Input
                        id="organization_name"
                        v-model="form.organization_name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        placeholder="e.g., Acme Corporation"
                    />
                    <InputError :message="form.errors.organization_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="organization_address">Organization Address</Label>
                    <textarea
                        id="organization_address"
                        v-model="form.organization_address"
                        required
                        :tabindex="2"
                        placeholder="Enter your organization's address..."
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                    ></textarea>
                    <InputError :message="form.errors.organization_address" />
                </div>

                <div class="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1"
                        :tabindex="4"
                        @click="$inertia.visit('/')"
                    >
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Back to Home
                    </Button>
                    <Button type="submit" class="flex-1" :tabindex="3" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                        Create Organization
                    </Button>
                </div>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="login()" class="underline underline-offset-4" :tabindex="5">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
