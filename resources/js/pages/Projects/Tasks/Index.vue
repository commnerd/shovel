<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, nextTick, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { ArrowLeft, Filter, CheckCircle, Clock, Circle, Calendar, Users, Layers, Plus, Edit, Trash2, TreePine, Leaf, GitBranch, Sparkles, GripVertical, AlertTriangle } from 'lucide-vue-next';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { BreadcrumbItem } from '@/types';

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    priority: 'low' | 'medium' | 'high';
    parent_id?: number;
    has_children: boolean;
    depth: number;
    is_top_level: boolean;
    is_leaf: boolean;
    sort_order: number;
    initial_order_index?: number;
    move_count?: number;
    current_order_index?: number;
    created_at: string;
}

interface Project {
    id: number;
    description: string;
    due_date?: string;
    status: string;
}

interface TaskCounts {
    all: number;
    top_level: number;
    leaf: number;
}

const page = usePage();
const project = computed(() => page.props.project as Project);
const tasks = computed(() => page.props.tasks as Task[]);
const currentFilter = computed(() => page.props.filter as string);
const taskCounts = computed(() => page.props.taskCounts as TaskCounts);

// Drag and drop state
const isDragging = ref(false);
const draggedTaskId = ref<number | null>(null);
const taskListRef = ref<HTMLElement>();
const showConfirmDialog = ref(false);
const confirmationData = ref<any>(null);
const pendingReorder = ref<{ taskId: number; newPosition: number } | null>(null);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const isRefreshingSortable = ref(false);

const filterOptions = [
    { value: 'all', label: 'All Tasks', icon: Layers, count: taskCounts.value.all },
    { value: 'top-level', label: 'Top-Level', icon: Circle, count: taskCounts.value.top_level },
    { value: 'leaf', label: 'Leaf Tasks', icon: CheckCircle, count: taskCounts.value.leaf },
];

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle;
        case 'in_progress': return Clock;
        default: return Circle;
    }
};

const getTaskTypeIcon = (task: Task) => {
    if (task.is_top_level && task.has_children) return TreePine;
    if (task.has_children) return GitBranch;
    if (task.is_leaf) return Leaf;
    return Circle;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'text-green-600 bg-green-50 border-green-200';
        case 'in_progress': return 'text-blue-600 bg-blue-50 border-blue-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'text-red-600 bg-red-50 border-red-200';
        case 'medium': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
        case 'low': return 'text-green-600 bg-green-50 border-green-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
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
                filter: currentFilter.value,
            }),
        });

        // Check if response is ok
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server error (${response.status}): ${errorText}`);
        }

        const result = await response.json();

        if (result.requires_confirmation && !confirmed) {
            confirmationData.value = result.confirmation_data;
            pendingReorder.value = { taskId, newPosition };
            showConfirmDialog.value = true;
            errorMessage.value = null; // Clear any previous errors
            return false;
        }

        if (result.success) {
            // Update tasks list with new data
            successMessage.value = result.message || 'Task reordered successfully!';
            errorMessage.value = null;

            // Update the local tasks array with the new data from the server
            if (result.tasks) {
                // This will trigger the watcher and reinitialize sortable
                tasks.value = result.tasks;

                // Force aggressive sortable refresh after DOM update
                setTimeout(() => {
                    forceSortableRefresh();
                }, 200);
            } else {
                // Fallback: Force a page reload if no tasks data provided
                router.visit(window.location.href, {
                    preserveScroll: true,
                    only: ['tasks'],
                });
            }

            // Clear success message after a few seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 3000);

            return true;
        }

        console.error('Reorder failed:', result.message);
        console.error('Reorder details:', result);

        let errorMsg = result.message || 'Failed to reorder task. Please try again.';
        if (result.actual_position && result.new_position && result.actual_position !== result.new_position) {
            errorMsg += ` (Task is at position ${result.actual_position}, expected ${result.new_position})`;
        }

        errorMessage.value = errorMsg;
        successMessage.value = null;
        return false;
    } catch (error) {
        console.error('Reorder error:', error);

        // Try to extract error message from response
        let errorMsg = 'Network error occurred while reordering. Please check your connection and try again.';

        if (error instanceof Error) {
            errorMsg = error.message;
        } else if (typeof error === 'string') {
            errorMsg = error;
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
            // Error message is already set by handleReorder
            showConfirmDialog.value = false;
            confirmationData.value = null;
            pendingReorder.value = null;
            return;
        }

        // Ensure sortable is refreshed after confirmation
        setTimeout(() => {
            forceSortableRefresh();
        }, 300);
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

    // Reset the visual order by reloading tasks
    router.visit(window.location.href, {
        preserveScroll: true,
        only: ['tasks'],
        onFinish: () => {
            // Ensure sortable is reinitialized after cancel
            nextTick(() => {
                setTimeout(() => {
                    initializeSortable();
                }, 200);
            });
        }
    });
};

const clearMessages = () => {
    errorMessage.value = null;
    successMessage.value = null;
};

const onDragStart = (taskId: number) => {
    isDragging.value = true;
    draggedTaskId.value = taskId;
};

const onDragEnd = () => {
    isDragging.value = false;
    draggedTaskId.value = null;
};

const shouldHideChildren = (task: Task) => {
    if (!isDragging.value) return false;

    const draggedTask = tasks.value.find(t => t.id === draggedTaskId.value);
    if (!draggedTask) return false;

    // Only hide children/subtasks of other tasks, never the top-level draggable tasks
    // This task should be hidden if:
    // 1. It has a parent (it's a child task)
    // 2. Its parent is NOT the dragged task (so we're not hiding children of the dragged task)
    // 3. Its parent is a sibling of the dragged task (same level as dragged task)

    if (task.parent_id && task.parent_id !== draggedTaskId.value) {
        const parentTask = tasks.value.find(t => t.id === task.parent_id);
        if (parentTask) {
            // Hide this child if its parent is a sibling of the dragged task
            return parentTask.parent_id === draggedTask.parent_id;
        }
    }

    return false;
};

// Store sortable instance from VueUse useSortable
let sortableInstance: { stop: () => void } | null = null;
let isInitializing = false;

// Key to force task list re-rendering when needed
const taskListKey = ref(0);
const forceTaskListRefresh = () => {
    taskListKey.value += 1;
    console.log('Forcing task list re-render with key:', taskListKey.value);
};

// Initialize sortable with proper error handling and debouncing
const initializeSortable = async () => {
    // Prevent multiple simultaneous initializations
    if (isInitializing) {
        return;
    }

    isInitializing = true;

    try {
        // Stop existing sortable instance
        if (sortableInstance) {
            sortableInstance.stop();
            sortableInstance = null;
        }

        // Wait for DOM to be ready
        await nextTick();

        // Double-check DOM element exists and is ready
        if (taskListRef.value && (currentFilter.value === 'all' || currentFilter.value === 'top-level')) {
            // Add a small delay to ensure DOM is fully rendered
            await new Promise(resolve => setTimeout(resolve, 50));

            // VueUse useSortable returns an object with a stop method
            sortableInstance = useSortable(taskListRef, tasks.value, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
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

            console.log('Sortable initialized successfully', {
                taskCount: tasks.value.length,
                filter: currentFilter.value,
                dragHandles: document.querySelectorAll('.drag-handle').length,
                timestamp: new Date().toISOString()
            });

            // Add debug function to window for testing
            (window as any).debugSortable = () => {
                console.log('Sortable Debug Info:', {
                    hasInstance: !!sortableInstance,
                    instanceType: typeof sortableInstance,
                    element: !!taskListRef.value,
                    elementChildren: taskListRef.value?.children.length,
                    filter: currentFilter.value,
                    taskCount: tasks.value.length,
                    isInitializing,
                    dragHandles: document.querySelectorAll('.drag-handle').length,
                    timestamp: new Date().toISOString()
                });
            };
        }
    } catch (error) {
        console.error('Failed to initialize sortable:', error);
    } finally {
        isInitializing = false;
    }
};

// Force a complete sortable refresh (more aggressive than normal initialization)
const forceSortableRefresh = async () => {
    console.log('Forcing sortable refresh...');
    isRefreshingSortable.value = true;

    try {
        // Stop existing instance
        if (sortableInstance) {
            try {
                sortableInstance.stop();
            } catch (e) {
                console.warn('Error stopping sortable instance:', e);
            }
            sortableInstance = null;
        }

        // Force task list re-render to ensure clean DOM state
        forceTaskListRefresh();

        // Wait for DOM to settle after re-render
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 200));

        // Reinitialize
        await initializeSortable();

        console.log('Sortable refresh completed');
    } finally {
        isRefreshingSortable.value = false;
    }
};

// Debounced initialization to prevent rapid re-initialization
let initTimeout: NodeJS.Timeout | null = null;

const debouncedInitialize = () => {
    if (initTimeout) {
        clearTimeout(initTimeout);
    }

    initTimeout = setTimeout(() => {
        initializeSortable();
    }, 100);
};

// Watch for changes to reinitialize sortable
watch([tasks, currentFilter], (newValues, oldValues) => {
    const [newTasks, newFilter] = newValues;
    const [oldTasks, oldFilter] = oldValues || [[], ''];

    // Log for debugging
    console.log('Tasks/filter changed:', {
        taskCount: newTasks.length,
        filter: newFilter,
        tasksChanged: newTasks !== oldTasks,
        filterChanged: newFilter !== oldFilter
    });

    debouncedInitialize();
}, { immediate: false, deep: true }); // Deep watch to catch task array changes

// Initialize on mount
onMounted(() => {
    // Give the DOM time to fully render before initializing
    setTimeout(() => {
        initializeSortable();
    }, 200);
});

// Cleanup on unmount
onUnmounted(() => {
    if (initTimeout) {
        clearTimeout(initTimeout);
    }

    if (sortableInstance) {
        sortableInstance.stop();
        sortableInstance = null;
    }
});

const changeFilter = (filter: string) => {
    router.get(`/dashboard/projects/${project.value.id}/tasks`, { filter }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Mock some tasks if none exist
const hasTasks = computed(() => tasks.value.length > 0);

const deleteTask = (taskId: number) => {
    if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        router.delete(`/dashboard/projects/${project.value.id}/tasks/${taskId}`, {
            preserveScroll: true,
        });
    }
};

// No longer needed - AI breakdown is now on dedicated page

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
];
</script>

<template>
    <Head :title="`Tasks - ${project.title || 'Untitled Project'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="space-y-6">
            <!-- Header with back button -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/dashboard/projects" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Projects
                        </Link>
                    </Button>
                    <Separator orientation="vertical" class="h-6" />
                    <div>
                        <Heading class="mb-1">Project Tasks</Heading>
                        <p class="text-sm text-gray-600">{{ project.description }}</p>
                        <div v-if="project.due_date" class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <Calendar class="h-4 w-4" />
                            Due: {{ new Date(project.due_date).toLocaleDateString() }}
                        </div>
                    </div>
                </div>
                <Button class="flex items-center gap-2" as-child>
                    <Link :href="`/dashboard/projects/${project.id}/tasks/create`">
                        <Plus class="h-4 w-4" />
                        New Task
                    </Link>
                </Button>
            </div>

            <!-- Filter tabs -->
            <div class="flex items-center gap-2 border-b">
                <Filter class="h-4 w-4 text-gray-500" />
                <span class="text-sm font-medium text-gray-700 mr-2">Filter:</span>
                <div class="flex gap-1">
                    <Button
                        v-for="option in filterOptions"
                        :key="option.value"
                        :variant="currentFilter === option.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="changeFilter(option.value)"
                        class="flex items-center gap-2"
                    >
                        <component :is="option.icon" class="h-4 w-4" />
                        {{ option.label }}
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">{{ option.count }}</span>
                    </Button>
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

            <!-- Tasks list -->
            <div v-if="hasTasks" class="space-y-2" ref="taskListRef" :key="taskListKey">
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    :data-task-id="task.id"
                    class="flex items-center gap-3 p-3 rounded-lg border bg-white hover:shadow-md transition-shadow"
                    :class="{
                        'ml-6': task.depth > 0 && currentFilter !== 'leaf',
                        'opacity-50': shouldHideChildren(task),
                        'cursor-grabbing': isDragging && draggedTaskId === task.id,
                        'cursor-grab': !isDragging && (currentFilter === 'all' || currentFilter === 'top-level')
                    }"
                    v-show="!shouldHideChildren(task)"
                >
                    <!-- Drag handle - show for 'all' and 'top-level' filters -->
                    <div
                        v-if="currentFilter === 'all' || currentFilter === 'top-level'"
                        class="drag-handle flex-shrink-0 cursor-grab hover:text-gray-600 text-gray-400"
                        :class="{ 'cursor-grabbing': isDragging && draggedTaskId === task.id }"
                    >
                        <GripVertical class="h-4 w-4" />
                    </div>
                    <!-- Status and type icons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <component
                            :is="getStatusIcon(task.status)"
                            class="h-4 w-4"
                            :class="getStatusColor(task.status).split(' ')[0]"
                        />
                        <component
                            :is="getTaskTypeIcon(task)"
                            class="h-3 w-3 text-gray-400"
                        />
                    </div>

                    <!-- Task title -->
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-gray-900 truncate block">{{ task.title }}</span>
                    </div>

                    <!-- Priority badge - hidden on small screens -->
                    <span
                        :class="getPriorityColor(task.priority)"
                        class="hidden sm:inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                    >
                        {{ task.priority }}
                    </span>

                    <!-- Status badge - hidden on small screens -->
                    <span
                        :class="getStatusColor(task.status)"
                        class="hidden sm:inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                    >
                        {{ task.status.replace('_', ' ') }}
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <!-- Subtasks group -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium">Subtasks:</span>
                            <div class="flex items-center border rounded-md">
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-r-none border-r">
                                    <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" class="flex items-center gap-1">
                                        <Plus class="h-3 w-3" />
                                        <span class="text-xs hidden sm:inline">Add</span>
                                    </Link>
                                </Button>
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-l-none">
                                    <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/breakdown`" class="flex items-center gap-1">
                                        <Sparkles class="h-3 w-3" />
                                        <span class="text-xs hidden sm:inline">Generate</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Edit and Delete buttons -->
                        <div class="flex items-center gap-1">
                            <Button size="sm" variant="ghost" as-child class="h-8 w-8 p-0">
                                <Link :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`">
                                    <Edit class="h-3 w-3" />
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                @click="deleteTask(task.id)"
                            >
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!hasTasks" class="flex items-center justify-center min-h-[400px]">
                <div class="text-center max-w-md mx-auto">
                    <div class="mb-6">
                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <CheckCircle class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No tasks yet</h3>
                        <p class="text-gray-500 mb-6">
                            This project doesn't have any tasks yet.
                            <span v-if="currentFilter !== 'all'">
                                Try changing the filter or create some tasks to get started.
                            </span>
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Button @click="changeFilter('all')" variant="outline" class="w-full">
                            View All Tasks
                        </Button>
                        <Button variant="default" class="w-full" as-child>
                            <Link :href="`/dashboard/projects/${project.id}/tasks/create`">
                                Create First Task
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            </div>

            <!-- Priority Confirmation Dialog -->
            <Dialog v-model:open="showConfirmDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Task Reordering</DialogTitle>
                        <DialogDescription>
                            {{ confirmationData?.message }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="confirmationData" class="py-4">
                        <div class="space-y-2">
                            <p class="text-sm">
                                <strong>Task Priority:</strong>
                                <span :class="getPriorityColor(confirmationData.task_priority)" class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ml-2">
                                    {{ confirmationData.task_priority }}
                                </span>
                            </p>
                            <p class="text-sm">
                                <strong>Neighbor Priorities:</strong>
                                <span v-for="priority in confirmationData.neighbor_priorities" :key="priority" :class="getPriorityColor(priority)" class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ml-2">
                                    {{ priority }}
                                </span>
                            </p>
                        </div>
                    </div>

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

/* Improve drag handle visibility */
.drag-handle:hover {
    background-color: #f3f4f6;
    border-radius: 4px;
}

/* Ensure draggable items have proper cursor */
.cursor-grab:hover .drag-handle {
    cursor: grab;
}

.cursor-grabbing .drag-handle {
    cursor: grabbing;
}
</style>
