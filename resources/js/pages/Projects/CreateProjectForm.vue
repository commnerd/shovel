<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Define emits
const emit = defineEmits<{
    success: []
}>();
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Sparkles, Send, Calendar } from 'lucide-vue-next';

const form = useForm({
    description: '',
    due_date: '',
});

const isSubmitting = ref(false);

const submit = () => {
    if (form.processing) return;

    isSubmitting.value = true;

    form.post('/dashboard/projects', {
        onFinish: () => {
            isSubmitting.value = false;
        },
        onSuccess: () => {
            form.reset();
            emit('success');
        },
    });
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        submit();
    }
};
</script>

<template>
    <Card class="w-full max-w-lg mx-auto" data-testid="create-project-form">
        <CardHeader class="text-center">
            <CardTitle class="flex items-center justify-center gap-2">
                <Sparkles class="h-5 w-5 text-blue-600" />
                Create New Project
            </CardTitle>
            <CardDescription>
                Describe your project and let AI create an initial task layout for you
            </CardDescription>
        </CardHeader>

        <form @submit.prevent="submit">
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="description">Project Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        placeholder="e.g., Build a task management app with Vue.js and Laravel..."
                        class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                        :disabled="form.processing"
                        @keydown="handleKeydown"
                        data-testid="project-description"
                        required
                    ></textarea>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-2">
                    <Label for="due_date" class="flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Due Date (Optional)
                    </Label>
                    <Input
                        id="due_date"
                        v-model="form.due_date"
                        type="date"
                        :disabled="form.processing"
                        class="w-full"
                    />
                    <InputError :message="form.errors.due_date" />
                    <p class="text-xs text-gray-500">
                        Leave empty if no specific deadline
                    </p>
                </div>

                <div class="text-xs text-gray-500 pt-2">
                    Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 rounded">⌘ + Enter</kbd> to submit
                </div>
            </CardContent>

            <CardFooter>
                <Button
                    type="submit"
                    class="w-full flex items-center gap-2"
                    :disabled="form.processing || !form.description.trim()"
                >
                    <Send v-if="!isSubmitting" class="h-4 w-4" />
                    <div v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                    {{ isSubmitting ? 'Creating with AI...' : 'Create Project' }}
                </Button>
            </CardFooter>
        </form>
    </Card>
</template>

<style scoped>
kbd {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
}
</style>
