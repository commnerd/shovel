<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, nextTick, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ArrowLeft, CheckCircle, Clock, Circle, Calendar, GripVertical, AlertTriangle, CheckSquare, Edit } from 'lucide-vue-next';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { BreadcrumbItem } from '@/types';

interface Subtask {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    parent_id?: number;
    due_date?: string;
    has_children: boolean;
    depth: number;
    is_top_level: boolean;
    is_leaf: boolean;
    sort_order: number;
    completion_percentage: number;
    created_at: string;
}

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    parent_id?: number;
    due_date?: string;
    has_children: boolean;
    depth: number;
    is_top_level: boolean;
    is_leaf: boolean;
}

interface Project {
    id: number;
    title: string;
    description: string;
}

const page = usePage();
const project = computed(() => page.props.project as Project);
const task = computed(() => page.props.task as Task);
const subtasks = computed(() => page.props.subtasks as Subtask[]);

// Drag and drop state
const isDragging = ref(false);
const draggedTaskId = ref<number | null>(null);
const taskListRef = ref<HTMLElement>();
const showConfirmDialog = ref(false);
const confirmationData = ref<any>(null);
const pendingReorder = ref<{ taskId: number; newPosition: number } | null>(null);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle;
        case 'in_progress': return Clock;
        default: return Circle;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'text-green-600 bg-green-50 border-green-200';
        case 'in_progress': return 'text-blue-600 bg-blue-50 border-blue-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

const toggleTaskStatus = async (subtask: Subtask) => {
    const newStatus = subtask.status === 'completed' ? 'pending' : 'completed';

    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/${subtask.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                status: newStatus,
            }),
        });

        if (!response.ok) {
            throw new Error(`Server error (${response.status})`);
        }

        const result = await response.json();

        if (result.success) {
            successMessage.value = `Subtask marked as ${newStatus === 'completed' ? 'complete' : 'incomplete'}!`;
            errorMessage.value = null;

            // Refresh the page to get updated subtask status
            router.visit(window.location.href, {
                preserveScroll: true,
                only: ['subtasks'],
            });

            // Clear success message after 2 seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 2000);
        } else {
            throw new Error(result.message || 'Failed to update subtask status');
        }
    } catch (error) {
        console.error('Failed to toggle subtask status:', error);
        errorMessage.value = 'Failed to update subtask status. Please try again.';

        // Clear error message after 5 seconds
        setTimeout(() => {
            errorMessage.value = null;
        }, 5000);
    }
};

// Drag and drop functions
const handleReorder = async (taskId: number, newPosition: number, confirmed = false) => {
    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/${taskId}/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                new_position: newPosition,
                confirmed,
                filter: 'subtasks', // Use 'subtasks' filter for subtask reordering context
            }),
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server error (${response.status}): ${errorText}`);
        }

        const result = await response.json();

        if (result.requires_confirmation && !confirmed) {
            confirmationData.value = result.confirmation_data;
            pendingReorder.value = { taskId, newPosition };
            showConfirmDialog.value = true;
            errorMessage.value = null;
            return false;
        }

        if (result.success) {
            let message = result.message || 'Subtask reordered successfully!';
            successMessage.value = message;
            errorMessage.value = null;

            // Refresh the page to get updated subtask order
            router.visit(window.location.href, {
                preserveScroll: true,
                only: ['subtasks'],
            });

            // Clear success message after a few seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 3000);

            return true;
        }

        console.error('Reorder failed:', result.message);
        let errorMsg = result.message || 'Failed to reorder subtask. Please try again.';
        errorMessage.value = errorMsg;
        successMessage.value = null;
        return false;
    } catch (error) {
        console.error('Reorder error:', error);
        let errorMsg = 'Network error occurred while reordering. Please check your connection and try again.';
        if (error instanceof Error) {
            errorMsg = error.message;
        }
        errorMessage.value = errorMsg;
        successMessage.value = null;
        return false;
    }
};

const confirmReorder = async () => {
    if (pendingReorder.value) {
        const success = await handleReorder(pendingReorder.value.taskId, pendingReorder.value.newPosition, true);
        if (!success) {
            showConfirmDialog.value = false;
            confirmationData.value = null;
            pendingReorder.value = null;
            return;
        }
    }
    showConfirmDialog.value = false;
    confirmationData.value = null;
    pendingReorder.value = null;
};

const cancelReorder = () => {
    showConfirmDialog.value = false;
    confirmationData.value = null;
    pendingReorder.value = null;
    errorMessage.value = null;

    // Reset the visual order by reloading subtasks
    router.visit(window.location.href, {
        preserveScroll: true,
        only: ['subtasks'],
    });
};

const onDragStart = (taskId: number) => {
    isDragging.value = true;
    draggedTaskId.value = taskId;
};

const onDragEnd = () => {
    isDragging.value = false;
    draggedTaskId.value = null;
};

// Store sortable instance
let sortableInstance: { stop: () => void } | null = null;

// Initialize sortable
const initializeSortable = async () => {
    try {
        // Stop existing sortable instance
        if (sortableInstance) {
            sortableInstance.stop();
            sortableInstance = null;
        }

        // Wait for DOM to be ready
        await nextTick();

        if (taskListRef.value && subtasks.value.length > 0) {
            // Add a small delay to ensure DOM is fully rendered
            await new Promise(resolve => setTimeout(resolve, 50));

            sortableInstance = useSortable(taskListRef, subtasks.value, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                touchStartThreshold: 5, // Enable touch support with threshold
                forceFallback: false, // Use native drag on supported devices
                fallbackOnBody: true, // Better touch experience
                swapThreshold: 0.65, // Better touch interaction
                onStart: (evt) => {
                    const taskId = parseInt(evt.item.dataset.taskId || '0');
                    onDragStart(taskId);
                },
                onEnd: async (evt) => {
                    onDragEnd();
                    if (evt.newIndex !== evt.oldIndex && evt.item.dataset.taskId) {
                        const taskId = parseInt(evt.item.dataset.taskId);
                        const newPosition = evt.newIndex! + 1; // Convert to 1-based index
                        await handleReorder(taskId, newPosition);
                    }
                },
            });
        }
    } catch (error) {
        console.error('Failed to initialize sortable:', error);
    }
};

// Initialize on mount
onMounted(() => {
    setTimeout(() => {
        initializeSortable();
    }, 200);
});

// Cleanup on unmount
onUnmounted(() => {
    if (sortableInstance) {
        sortableInstance.stop();
        sortableInstance = null;
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
    },
    {
        title: project.value.title || 'Untitled Project',
        href: `/dashboard/projects/${project.value.id}/tasks`,
    },
    {
        title: task.value.title || 'Untitled Task',
        href: `/dashboard/projects/${project.value.id}/tasks/${task.value.id}/subtasks/reorder`,
    },
];
</script>

<template>
    <Head :title="`Reorder Subtasks - ${task.title || 'Untitled Task'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="space-y-6">
                <!-- Header with back button -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="`/dashboard/projects/${project.id}/tasks`" class="flex items-center gap-2">
                                <ArrowLeft class="h-4 w-4" />
                                Back to Tasks
                            </Link>
                        </Button>
                        <Separator orientation="vertical" class="h-6" />
                        <div>
                            <Heading title="Reorder Subtasks" class="mb-1" />
                            <p class="text-sm text-gray-600 hidden sm:block">{{ task.title }}</p>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-4 w-4 text-green-600" />
                        <span class="text-sm font-medium text-green-800">{{ successMessage }}</span>
                    </div>
                </div>

                <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-4 w-4 text-red-600" />
                        <span class="text-sm font-medium text-red-800">{{ errorMessage }}</span>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="errorMessage = null"
                            class="ml-auto h-6 w-6 p-0 text-red-600 hover:text-red-800"
                        >
                            ✕
                        </Button>
                    </div>
                </div>

                <!-- Subtasks List -->
                <div v-if="subtasks.length > 0" class="space-y-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <CheckSquare class="h-5 w-5" />
                                Subtasks ({{ subtasks.length }})
                            </CardTitle>
                            <CardDescription>
                                Drag and drop to reorder subtasks. The order will be saved automatically.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div ref="taskListRef" class="space-y-2">
                                <div
                                    v-for="subtask in subtasks"
                                    :key="subtask.id"
                                    :data-task-id="subtask.id"
                                    class="flex items-center gap-3 p-3 rounded-lg border bg-white hover:shadow-md transition-all duration-300 cursor-grab"
                                    :class="{
                                        'cursor-grabbing': isDragging && draggedTaskId === subtask.id,
                                    }"
                                >
                                    <!-- Drag handle -->
                                    <div
                                        class="drag-handle flex-shrink-0 cursor-grab hover:text-gray-600 text-gray-400"
                                        :class="{ 'cursor-grabbing': isDragging && draggedTaskId === subtask.id }"
                                    >
                                        <GripVertical class="h-4 w-4" />
                                    </div>

                                    <!-- Status indicator -->
                                    <div class="flex items-center justify-center w-5 h-5 flex-shrink-0">
                                        <button
                                            @click="toggleTaskStatus(subtask)"
                                            class="flex items-center justify-center w-5 h-5 rounded-full border-2 transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                            :class="subtask.status === 'completed'
                                                ? 'bg-green-500 border-green-500 text-white'
                                                : 'border-gray-300 hover:border-green-400 bg-white'"
                                            :title="subtask.status === 'completed' ? 'Mark as incomplete' : 'Mark as complete'"
                                        >
                                            <CheckCircle
                                                v-if="subtask.status === 'completed'"
                                                class="h-3 w-3"
                                            />
                                        </button>
                                    </div>

                                    <!-- Subtask title -->
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-gray-900 truncate block">{{ subtask.title }}</span>
                                    </div>

                                    <!-- Due date if exists -->
                                    <div v-if="subtask.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                                        <Calendar class="h-3 w-3" />
                                        {{ new Date(subtask.due_date).toLocaleDateString() }}
                                    </div>

                                    <!-- Status badge -->
                                    <span
                                        :class="getStatusColor(subtask.status)"
                                        class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                                    >
                                        {{ subtask.status.replace('_', ' ') }}
                                    </span>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-1">
                                        <Button size="sm" variant="ghost" as-child class="h-8 w-8 p-0">
                                            <Link :href="`/dashboard/projects/${project.id}/tasks/${subtask.id}/edit`">
                                                <Edit class="h-3 w-3" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty state -->
                <div v-else class="flex items-center justify-center min-h-[400px]">
                    <div class="text-center max-w-md mx-auto">
                        <div class="mb-6">
                            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <CheckSquare class="h-8 w-8 text-gray-400" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No subtasks yet</h3>
                            <p class="text-gray-500 mb-6">
                                This task doesn't have any subtasks yet.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <Button variant="default" class="w-full" as-child>
                                <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`">
                                    Create First Subtask
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reorder Confirmation Dialog -->
            <Dialog v-model:open="showConfirmDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Subtask Reordering</DialogTitle>
                        <DialogDescription>
                            {{ confirmationData?.message }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button variant="outline" @click="cancelReorder">Cancel</Button>
                        <Button @click="confirmReorder">Continue</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Sortable drag states for better visual feedback */
.sortable-ghost {
    opacity: 0.4;
    background: #f3f4f6;
}

.sortable-chosen {
    cursor: grabbing !important;
}

.sortable-drag {
    opacity: 0.8;
    transform: rotate(5deg);
}

/* Improve drag handle visibility and touch interaction */
.drag-handle:hover,
.drag-handle:active {
    background-color: #f3f4f6;
    border-radius: 4px;
}

/* Better touch targets for mobile */
.drag-handle {
    min-width: 32px;
    min-height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
}

/* Touch-friendly cursor */
@media (hover: none) and (pointer: coarse) {
    .drag-handle {
        cursor: default;
        touch-action: none; /* Prevent scrolling while dragging */
    }

    /* Better visual feedback for touch */
    .drag-handle:active {
        background-color: #e5e7eb;
        transform: scale(1.05);
    }
}
</style>
