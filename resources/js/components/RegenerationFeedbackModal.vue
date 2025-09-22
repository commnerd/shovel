<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Sparkles class="h-5 w-5 text-blue-600" />
                    Regenerate with Feedback
                </DialogTitle>
                <DialogDescription>
                    Provide specific instructions to help the AI generate better {{ taskType }}.
                    Be as detailed as possible about what you'd like to change.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Current Results Summary -->
                <div v-if="currentResults.length > 0" class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Current {{ taskType }} ({{ currentResults.length }}):</h4>
                    <div class="space-y-1 max-h-32 overflow-y-auto">
                        <div v-for="(item, index) in currentResults" :key="index" class="text-sm text-gray-600">
                            {{ index + 1 }}. {{ item.title }}
                        </div>
                    </div>
                </div>

                <!-- Feedback Input -->
                <div class="space-y-2">
                    <Label for="feedback">How should the AI improve the {{ taskType }}?</Label>
                    <textarea
                        id="feedback"
                        v-model="feedbackText"
                        placeholder="Example: Make the tasks more specific, add testing phases, focus on security aspects, break down into smaller steps, etc."
                        class="min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                        :disabled="isProcessing"
                    />
                </div>

                <!-- Quick Feedback Options -->
                <div class="space-y-2">
                    <Label class="text-sm font-medium">Quick suggestions (click to add):</Label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="suggestion in quickSuggestions"
                            :key="suggestion"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addSuggestion(suggestion)"
                            :disabled="isProcessing"
                            class="text-xs"
                        >
                            {{ suggestion }}
                        </Button>
                    </div>
                </div>

                <!-- Context Information -->
                <div v-if="context" class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <h5 class="text-sm font-medium text-blue-900 mb-1">Context Information:</h5>
                    <div class="text-xs text-blue-700 space-y-1">
                        <div v-if="context.projectTitle">Project: {{ context.projectTitle }}</div>
                        <div v-if="context.existingTasksCount">Existing tasks: {{ context.existingTasksCount }}</div>
                        <div v-if="context.completedTasksCount !== undefined">Completed: {{ context.completedTasksCount }}</div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="handleCancel"
                    :disabled="isProcessing"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    @click="handleRegenerate"
                    :disabled="isProcessing || !feedbackText.trim()"
                    class="flex items-center gap-2"
                >
                    <Loader v-if="isProcessing" class="h-4 w-4 animate-spin" />
                    <Sparkles v-else class="h-4 w-4" />
                    {{ isProcessing ? 'Regenerating...' : 'Regenerate' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Sparkles, Loader } from 'lucide-vue-next';

interface TaskItem {
    title: string;
    description?: string;
    status?: string;
}

interface RegenerationContext {
    projectTitle?: string;
    existingTasksCount?: number;
    completedTasksCount?: number;
    taskTitle?: string;
}

interface Props {
    open: boolean;
    taskType: 'tasks' | 'subtasks';
    currentResults: TaskItem[];
    context?: RegenerationContext;
    isProcessing?: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'regenerate', feedback: string): void;
    (e: 'cancel'): void;
}

const props = withDefaults(defineProps<Props>(), {
    isProcessing: false,
});

const emit = defineEmits<Emits>();

const feedbackText = ref('');

const quickSuggestions = computed(() => {
    if (props.taskType === 'tasks') {
        return [
            'Make tasks more specific',
            'Add testing phases',
            'Include setup/deployment',
            'Focus on security',
            'Add documentation tasks',
            'Break into smaller tasks',
            'Add review/approval steps',
            'Include user research',
        ];
    } else {
        return [
            'Break down further',
            'Add more detail',
            'Include testing steps',
            'Add time estimates',
            'Focus on implementation',
            'Include error handling',
            'Add validation steps',
            'Make more actionable',
        ];
    }
});

const addSuggestion = (suggestion: string) => {
    if (feedbackText.value.trim()) {
        feedbackText.value += feedbackText.value.endsWith('.') || feedbackText.value.endsWith(',')
            ? ' ' + suggestion.toLowerCase() + '.'
            : ', ' + suggestion.toLowerCase() + '.';
    } else {
        feedbackText.value = suggestion + '.';
    }
};

const handleOpenChange = (value: boolean) => {
    if (!props.isProcessing) {
        emit('update:open', value);
        if (!value) {
            feedbackText.value = '';
        }
    }
};

const handleCancel = () => {
    if (!props.isProcessing) {
        feedbackText.value = '';
        emit('cancel');
    }
};

const handleRegenerate = () => {
    if (feedbackText.value.trim() && !props.isProcessing) {
        emit('regenerate', feedbackText.value.trim());
    }
};
</script>
