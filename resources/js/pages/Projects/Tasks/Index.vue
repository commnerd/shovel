<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, nextTick, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { ArrowLeft, Filter, CheckCircle, Clock, Circle, Calendar, Users, Layers, Plus, Edit, Trash2, TreePine, Leaf, GitBranch, Sparkles, GripVertical, AlertTriangle, List, BarChart3, CheckSquare, Kanban } from 'lucide-vue-next';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { BreadcrumbItem } from '@/types';
import TaskSizing from '@/components/TaskSizing.vue';

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'pending' | 'in_progress' | 'completed';
    parent_id?: number;
    iteration_id?: number;
    due_date?: string;
    has_children: boolean;
    depth: number;
    is_top_level: boolean;
    is_leaf: boolean;
    sort_order: number;
    initial_order_index?: number;
    move_count?: number;
    current_order_index?: number;
    completion_percentage: number;
    created_at: string;
    // Iterative project fields
    size?: 'xs' | 's' | 'm' | 'l' | 'xl';
    initial_story_points?: number;
    current_story_points?: number;
    story_points_change_count?: number;
    iteration?: {
        id: number;
        name: string;
        status: string;
    };
}

interface Project {
    id: number;
    title: string;
    description: string;
    due_date?: string;
    status: string;
    project_type: 'finite' | 'iterative';
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

// Kanban board state
const kanbanDraggedTask = ref<Task | null>(null);
const kanbanDragOverColumn = ref<string | null>(null);

// Column refs for height synchronization
const pendingColumnRef = ref<HTMLElement | null>(null);
const inProgressColumnRef = ref<HTMLElement | null>(null);
const completedColumnRef = ref<HTMLElement | null>(null);

// Dynamic column heights
const columnHeights = ref({
    pending: 'auto',
    in_progress: 'auto',
    completed: 'auto'
});

// Function to synchronize all column heights to the tallest one
const synchronizeColumnHeights = () => {
    if (currentFilter.value !== 'board') return;

    nextTick(() => {
        const columns = [
            { ref: pendingColumnRef, key: 'pending' },
            { ref: inProgressColumnRef, key: 'in_progress' },
            { ref: completedColumnRef, key: 'completed' }
        ];

        // Reset heights to auto first to get natural heights
        columnHeights.value = {
            pending: 'auto',
            in_progress: 'auto',
            completed: 'auto'
        };

        // Wait for DOM update, then measure
        nextTick(() => {
            let maxHeight = 300; // Minimum height

            // Find the tallest column
            columns.forEach(({ ref: columnRef }) => {
                if (columnRef.value) {
                    const height = columnRef.value.offsetHeight;
                    maxHeight = Math.max(maxHeight, height);
                }
            });

            // Apply the max height to all columns
            const heightPx = `${maxHeight}px`;
            columnHeights.value = {
                pending: heightPx,
                in_progress: heightPx,
                completed: heightPx
            };
        });
    });
};


const tabOptions = [
    {
        value: 'top-level',
        label: 'List',
        icon: List,
        count: taskCounts.value.top_level,
        description: 'Drag & drop reorderable view of top-level tasks only. Use this for organizing your main project structure.'
    },
    {
        value: 'all',
        label: 'Breakdown',
        icon: BarChart3,
        count: taskCounts.value.all,
        description: 'Complete hierarchical view showing all tasks and subtasks with visual indentation. Only leaf tasks can be marked complete.'
    },
    {
        value: 'leaf',
        label: 'Todo',
        icon: CheckSquare,
        count: taskCounts.value.leaf,
        description: 'Simplified list of actionable tasks only (no parent tasks). Perfect for focusing on work that can be completed today.'
    },
    {
        value: 'board',
        label: 'Board',
        icon: Kanban,
        count: taskCounts.value.leaf,
        description: 'Kanban-style board with To Do, In Progress, and Done columns. Drag tasks between columns to update their status.'
    },
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

// Helper function to get CSRF token with error handling and refresh capability
const getCSRFToken = async (): Promise<string> => {
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!token) {
        // Try to refresh the token by making a request to a simple endpoint
        try {
            const response = await fetch('/dashboard', {
                method: 'GET',
                credentials: 'same-origin'
            });
            if (response.ok) {
                // Token should be refreshed in the meta tag now
                token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            }
        } catch (e) {
            console.error('Failed to refresh CSRF token:', e);
        }
    }

    if (!token) {
        throw new Error('CSRF token not found. Please refresh the page and try again.');
    }

    return token;
};

// Drag and drop functions
const handleReorder = async (taskId: number, newPosition: number, confirmed = false) => {
    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/${taskId}/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': await getCSRFToken(),
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
            let message = result.message || 'Task reordered successfully!';

            successMessage.value = message;
            errorMessage.value = null;

            // Update the local tasks array with the new data from the server
            if (result.tasks) {
                // Normal reorder - create new array reference for consistency
                tasks.value = [...result.tasks];
                await nextTick();

                forceTaskListRefresh();
                await nextTick();

                setTimeout(async () => {
                    await forceSortableRefresh();
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

// Helper functions for Breakdown view
const getChildTaskCount = (parentTask: Task): number => {
    return tasks.value.filter(task => task.parent_id === parentTask.id).length;
};

const toggleTaskStatus = async (task: Task) => {
    const newStatus = task.status === 'completed' ? 'pending' : 'completed';

    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/${task.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': await getCSRFToken(),
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
            // Update the task status in the local array
            const taskIndex = tasks.value.findIndex(t => t.id === task.id);
            if (taskIndex !== -1) {
                tasks.value[taskIndex] = { ...tasks.value[taskIndex], status: newStatus };
            }

            successMessage.value = `Task marked as ${newStatus === 'completed' ? 'complete' : 'incomplete'}!`;
            errorMessage.value = null;

            // Clear success message after 2 seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 2000);
        } else {
            throw new Error(result.message || 'Failed to update task status');
        }
    } catch (error) {
        console.error('Failed to toggle task status:', error);
        errorMessage.value = 'Failed to update task status. Please try again.';

        // Clear error message after 5 seconds
        setTimeout(() => {
            errorMessage.value = null;
        }, 5000);
    }
};

// Refresh tasks data from server
const refreshTasks = () => {
    router.reload({ only: ['tasks'] });
};

// Kanban board drag and drop functions
const onKanbanDragStart = (event: DragEvent, task: Task) => {
    kanbanDraggedTask.value = task;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', task.id.toString());
    }
};

const onKanbanDragEnd = () => {
    kanbanDraggedTask.value = null;
    kanbanDragOverColumn.value = null;
};

const onKanbanDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onKanbanDragEnter = (event: DragEvent, status: string) => {
    event.preventDefault();
    kanbanDragOverColumn.value = status;
};

const onKanbanDragLeave = (event: DragEvent) => {
    // Only clear if we're leaving the column entirely
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX;
    const y = event.clientY;

    if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
        kanbanDragOverColumn.value = null;
    }
};

const onKanbanDrop = async (event: DragEvent, newStatus: string) => {
    event.preventDefault();

    if (!kanbanDraggedTask.value) return;

    const task = kanbanDraggedTask.value;

    // Don't update if status is the same
    if (task.status === newStatus) {
        kanbanDraggedTask.value = null;
        kanbanDragOverColumn.value = null;
        return;
    }

    try {
        const response = await fetch(`/dashboard/projects/${project.value.id}/tasks/${task.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': await getCSRFToken(),
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
            // Update the task status in the local array
            const taskIndex = tasks.value.findIndex(t => t.id === task.id);
            if (taskIndex !== -1) {
                tasks.value[taskIndex] = { ...tasks.value[taskIndex], status: newStatus };
            }

            successMessage.value = `Task moved to ${newStatus === 'pending' ? 'To Do' : newStatus === 'in_progress' ? 'In Progress' : 'Done'}!`;
            errorMessage.value = null;

            // Clear success message after 2 seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 2000);
        } else {
            throw new Error(result.message || 'Failed to update task status');
        }
    } catch (error) {
        console.error('Failed to update task status:', error);
        errorMessage.value = 'Failed to move task. Please try again.';

        // Clear error message after 5 seconds
        setTimeout(() => {
            errorMessage.value = null;
        }, 5000);
    } finally {
        kanbanDraggedTask.value = null;
        kanbanDragOverColumn.value = null;

        // Resync column heights after task movement
        synchronizeColumnHeights();
    }
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
        if (taskListRef.value && currentFilter.value === 'top-level') {
            // Add a small delay to ensure DOM is fully rendered
            await new Promise(resolve => setTimeout(resolve, 50));

            // VueUse useSortable returns an object with a stop method
            sortableInstance = useSortable(taskListRef, tasks.value, {
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

            // Sortable initialized successfully

            // Sortable initialization complete
        }
    } catch (error) {
        console.error('Failed to initialize sortable:', error);
    } finally {
        isInitializing = false;
    }
};

// Force a complete sortable refresh (more aggressive than normal initialization)
const forceSortableRefresh = async () => {
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


    debouncedInitialize();
}, { immediate: false, deep: true }); // Deep watch to catch task array changes

// Initialize on mount
onMounted(() => {
    // Give the DOM time to fully render before initializing
    setTimeout(() => {
        initializeSortable();
    }, 200);
});

// Watch for tasks changes to resync column heights
watch(() => tasks.value, () => {
    synchronizeColumnHeights();
}, { deep: true });

// Watch for filter changes to resync when switching to board view
watch(() => currentFilter.value, (newFilter) => {
    if (newFilter === 'board') {
        synchronizeColumnHeights();
    }
});

// Sync heights when component mounts and board view is active
onMounted(() => {
    if (currentFilter.value === 'board') {
        synchronizeColumnHeights();
    }
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
    if (project.value.id) {
        router.get(`/dashboard/projects/${project.value.id}/tasks`, { filter }, {
            preserveState: true,
            preserveScroll: true,
        });
    } else {
        console.error('Project ID is undefined, cannot change filter');
    }
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
                        <Heading title="Project Tasks" class="mb-1" />
                        <p class="text-sm text-gray-600 hidden sm:block">{{ project.description }}</p>
                        <div v-if="project.due_date" class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <Calendar class="h-4 w-4" />
                            Due: {{ new Date(project.due_date).toLocaleDateString() }}
                        </div>
                    </div>
                </div>
                <Button class="flex items-center gap-2" as-child>
                    <Link v-if="project.id" :href="`/dashboard/projects/${project.id}/tasks/create`">
                        <Plus class="h-4 w-4" />
                        New Task
                    </Link>
                </Button>
            </div>

            <!-- Filter tabs -->
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <!-- Mobile: Scrollable horizontal tabs -->
                <nav class="-mb-px flex overflow-x-auto scrollbar-hide" aria-label="Tabs">
                    <div class="flex space-x-1 sm:space-x-8 min-w-max px-2 sm:px-0">
                        <button
                            v-for="tab in tabOptions"
                            :key="tab.value"
                            @click="changeFilter(tab.value)"
                            :class="[
                                'group inline-flex items-center border-b-2 font-medium transition-colors duration-200 whitespace-nowrap',
                                'py-3 px-2 sm:py-4 sm:px-1 text-xs sm:text-sm',
                                currentFilter === tab.value
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                            :aria-current="currentFilter === tab.value ? 'page' : undefined"
                            :title="tab.description"
                        >
                            <component
                                :is="tab.icon"
                                :class="[
                                    'h-4 w-4 sm:h-5 sm:w-5',
                                    'mr-1 sm:mr-2',
                                    currentFilter === tab.value ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'
                                ]"
                            />
                            <span class="hidden sm:inline">{{ tab.label }}</span>
                            <span class="sm:hidden text-xs">{{ tab.label.charAt(0) }}</span>
                            <span
                                :class="[
                                    'ml-1 sm:ml-2 inline-flex items-center rounded-full text-xs font-medium',
                                    'px-1.5 py-0.5 sm:px-2.5 sm:py-0.5',
                                    currentFilter === tab.value
                                        ? 'bg-blue-100 text-blue-600'
                                        : 'bg-gray-100 text-gray-900'
                                ]"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </nav>
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

            <!-- Tab Content -->
            <div v-if="hasTasks" class="mt-6">

                <!-- List View (Top-Level Tasks) -->
                <div v-if="currentFilter === 'top-level'" class="space-y-2" ref="taskListRef" :key="taskListKey">
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    :data-task-id="task.id"
                    class="flex items-center gap-3 p-3 rounded-lg border bg-white hover:shadow-md transition-all duration-300"
                    :class="{
                        'opacity-50': shouldHideChildren(task),
                        'cursor-grabbing': isDragging && draggedTaskId === task.id,
                        'cursor-grab': !isDragging && currentFilter === 'top-level',
                    }"
                    v-show="!shouldHideChildren(task)"
                >
                    <!-- Drag handle - show only for 'top-level' (List) view -->
                    <div
                        v-if="currentFilter === 'top-level'"
                        class="drag-handle flex-shrink-0 cursor-grab hover:text-gray-600 text-gray-400"
                        :class="{ 'cursor-grabbing': isDragging && draggedTaskId === task.id }"
                    >
                        <GripVertical class="h-4 w-4" />
                    </div>
                    <!-- Status checkbox and type icons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Interactive status button for leaf tasks only -->
                        <button
                            v-if="task.is_leaf"
                            @click="toggleTaskStatus(task)"
                            class="flex items-center justify-center w-5 h-5 rounded-full border-2 transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                            :class="task.status === 'completed'
                                ? 'bg-green-500 border-green-500 text-white'
                                : 'border-gray-300 hover:border-green-400 bg-white'"
                            :title="task.status === 'completed' ? 'Mark as incomplete' : 'Mark as complete'"
                        >
                            <CheckCircle
                                v-if="task.status === 'completed'"
                                class="h-3 w-3"
                            />
                        </button>

                        <!-- Read-only status indicator for parent tasks -->
                        <div
                            v-else
                            class="flex items-center justify-center w-5 h-5 rounded-full border-2 cursor-not-allowed"
                            :class="task.status === 'completed'
                                ? 'bg-green-500 border-green-500 text-white'
                                : task.status === 'in_progress'
                                ? 'bg-blue-500 border-blue-500 text-white'
                                : 'border-gray-300 bg-gray-100'"
                            :title="`Parent task status (${task.status}) - determined by child tasks`"
                        >
                            <CheckCircle v-if="task.status === 'completed'" class="h-3 w-3 text-white" />
                            <Clock v-else-if="task.status === 'in_progress'" class="h-3 w-3 text-white" />
                            <Circle v-else class="h-3 w-3 text-gray-400" />
                        </div>
                                        <component
                                            :is="getTaskTypeIcon(task)"
                            :class="[
                                'h-3 w-3',
                                task.is_leaf ? 'text-green-600' : 'text-blue-600'
                            ]"
                                        />
                                    </div>

                    <!-- Task title -->
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-gray-900 truncate block">{{ task.title }}</span>
                    </div>


                    <!-- Status badge - hidden on small screens -->
                                        <span
                                            :class="getStatusColor(task.status)"
                        class="hidden sm:inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium flex-shrink-0"
                                        >
                                            {{ task.status.replace('_', ' ') }}
                                        </span>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 sm:gap-3 flex-shrink-0">
                        <!-- Subtasks group - hidden on very small screens -->
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium">Subtasks:</span>
                            <div class="flex items-center border rounded-md">
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-r-none border-r">
                                    <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" class="flex items-center gap-1" :title="task.is_leaf ? 'Add Subtask (will promote to parent)' : 'Add Subtask'">
                                        <Plus class="h-3 w-3" />
                                        <span class="text-xs hidden md:inline">Add</span>
                                    </Link>
                                </Button>
                                <Button size="sm" variant="ghost" as-child class="h-7 px-2 rounded-l-none">
                                    <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/breakdown`" class="flex items-center gap-1">
                                        <Sparkles class="h-3 w-3" />
                                        <span class="text-xs hidden md:inline">Generate</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Mobile: Compact action buttons -->
                        <div class="sm:hidden flex items-center gap-1">
                            <Button size="sm" variant="ghost" as-child class="h-6 w-6 p-0">
                                <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" :title="task.is_leaf ? 'Add Subtask (will promote to parent)' : 'Add Subtask'">
                                    <Plus class="h-3 w-3 sm:h-2.5 sm:w-2.5" />
                                </Link>
                            </Button>
                            <Button size="sm" variant="ghost" as-child class="h-6 w-6 p-0">
                                <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/breakdown`" title="AI Breakdown">
                                    <Sparkles class="h-3 w-3 sm:h-2.5 sm:w-2.5" />
                                </Link>
                            </Button>
                        </div>

                        <!-- Edit and Delete buttons -->
                        <div class="flex items-center gap-1">
                            <Button size="sm" variant="ghost" as-child class="h-6 w-6 sm:h-8 sm:w-8 p-0">
                                <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`" title="Edit Task">
                                    <Edit class="h-3 w-3 sm:h-3 sm:w-3" />
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-6 w-6 sm:h-8 sm:w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                @click="deleteTask(task.id)"
                                title="Delete Task"
                            >
                                <Trash2 class="h-3 w-3 sm:h-3 sm:w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Breakdown View (All Tasks as Hierarchical Lists) -->
                <div v-if="currentFilter === 'all'" class="space-y-1">
                    <div v-for="task in tasks" :key="task.id" class="task-breakdown-item">
                        <div
                            class="flex items-center gap-3 py-2 px-3 hover:bg-gray-50 rounded-lg transition-colors"
                            :class="{
                                'ml-4': task.depth === 1,
                                'ml-8': task.depth === 2,
                                'ml-12': task.depth === 3,
                                'ml-16': task.depth >= 4,
                                'bg-gray-25': !task.is_leaf,
                            }"
                        >
                            <!-- Hierarchy connector lines -->
                            <div v-if="task.depth > 0" class="flex items-center mr-2">
                                <div class="w-4 h-0.5 bg-gray-300"></div>
                                <div class="w-0.5 h-4 bg-gray-300 -ml-2 -mt-2"></div>
                            </div>

                            <!-- Status indicator - only interactive for leaf tasks -->
                            <div class="flex items-center justify-center w-4 h-4 flex-shrink-0">
                                <button
                                    v-if="task.is_leaf"
                                    @click="toggleTaskStatus(task)"
                                    class="flex items-center justify-center w-4 h-4 rounded border transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    :class="task.status === 'completed'
                                        ? 'bg-green-500 border-green-500 text-white'
                                        : 'border-gray-300 hover:border-green-400 bg-white'"
                                    :title="task.status === 'completed' ? 'Mark as incomplete' : 'Mark as complete'"
                                >
                                    <CheckCircle v-if="task.status === 'completed'" class="h-2.5 w-2.5" />
                                </button>

                                <!-- Read-only status indicator for parent tasks -->
                                <div
                                    v-else
                                    class="flex items-center justify-center w-4 h-4 rounded border cursor-not-allowed"
                                    :class="task.status === 'completed'
                                        ? 'bg-green-500 border-green-500 text-white'
                                        : task.status === 'in_progress'
                                        ? 'bg-blue-500 border-blue-500 text-white'
                                        : 'border-gray-300 bg-gray-100'"
                                    :title="`Parent task status (${task.status}) - automatically determined by child tasks`"
                                >
                                    <CheckCircle v-if="task.status === 'completed'" class="h-2.5 w-2.5 text-white" />
                                    <Clock v-else-if="task.status === 'in_progress'" class="h-2.5 w-2.5 text-white" />
                                    <Circle v-else class="h-2.5 w-2.5 text-gray-400" />
                                </div>
                                </div>

                            <!-- Task hierarchy indicator -->
                            <component :is="getTaskTypeIcon(task)"
                                :class="[
                                    'h-3 w-3 flex-shrink-0',
                                    task.is_leaf ? 'text-green-600' : 'text-blue-600'
                                ]" />

                            <!-- Task title with hierarchy styling -->
                            <Link
                                v-if="task.has_children && project.id && task.id"
                                :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/reorder`"
                                class="text-sm flex-1 hover:text-blue-600 transition-colors"
                                :class="[
                                    'text-gray-900',
                                    task.is_leaf ? 'font-medium' : 'font-semibold text-gray-800'
                                ]"
                            >
                                {{ task.title }}
                            </Link>
                            <span
                                v-else
                                class="text-sm flex-1"
                                :class="[
                                    task.status === 'completed' ? 'line-through text-gray-500' : 'text-gray-900',
                                    task.is_leaf ? 'font-medium' : 'font-semibold text-gray-800'
                                ]"
                            >
                                {{ task.title }}
                            </span>

                            <!-- Task sizing for all projects -->
                            <div class="ml-2">
                                <TaskSizing :task="task" @updated="refreshTasks" />
                            </div>

                            <!-- Task metadata -->
                            <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
                                <!-- Due date -->
                                <div v-if="task.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                                    <Calendar class="h-3 w-3" />
                                    <span class="hidden sm:inline">{{ new Date(task.due_date).toLocaleDateString() }}</span>
                                    <span class="sm:hidden">{{ new Date(task.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                                </div>

                                <!-- Status badge with different styling for parents -->
                                <span
                                    :class="[
                                        getStatusColor(task.status),
                                        task.is_leaf ? '' : 'opacity-75'
                                    ]"
                                    class="inline-flex items-center rounded-full border px-1.5 sm:px-2 py-0.5 text-xs font-medium"
                                    :title="task.is_leaf ? '' : 'Status determined by child tasks'"
                                >
                                    <span class="hidden sm:inline">{{ task.status }}</span>
                                    <span class="sm:hidden">{{ (task.status || 'pending').charAt(0).toUpperCase() }}</span>
                                </span>

                                <!-- Child task progress for parents -->
                                <span v-if="!task.is_leaf && task.has_children" class="text-xs text-gray-500">
                                    <span class="hidden sm:inline">{{ Math.round(task.completion_percentage) }}% complete</span>
                                    <span class="sm:hidden">{{ Math.round(task.completion_percentage) }}%</span>
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-0.5 sm:gap-1">
                                <Button size="sm" variant="ghost" as-child class="h-5 w-5 sm:h-6 sm:w-6 p-0">
                                    <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`" title="Edit Task">
                                        <Edit class="h-2.5 w-2.5" />
                                    </Link>
                                </Button>

                                <!-- Add subtask button for all tasks (promotes leaf tasks to parent tasks) -->
                                <Button size="sm" variant="ghost" as-child class="h-5 w-5 sm:h-6 sm:w-6 p-0">
                                    <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/subtasks/create`" :title="task.is_leaf ? 'Add Subtask (will promote to parent)' : 'Add Subtask'">
                                        <Plus class="h-2.5 w-2.5" />
                                    </Link>
                                </Button>

                                <!-- AI breakdown button for all tasks -->
                                <Button size="sm" variant="ghost" as-child class="h-5 w-5 sm:h-6 sm:w-6 p-0">
                                    <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/breakdown`" title="AI Breakdown">
                                        <Sparkles class="h-2.5 w-2.5" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Todo View (Hierarchical with Actionable Focus) -->
                <div v-if="currentFilter === 'leaf'" class="space-y-2">
                    <div
                        v-for="task in tasks.filter(t => t.is_leaf)"
                        :key="task.id"
                        class="flex items-center gap-3 p-3 rounded-lg border bg-white hover:shadow-sm transition-all duration-200"
                    >
                        <!-- Status checkbox for leaf tasks -->
                        <button
                            @click="toggleTaskStatus(task)"
                            class="flex items-center justify-center w-5 h-5 rounded-full border-2 transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                            :class="task.status === 'completed'
                                ? 'bg-green-500 border-green-500 text-white'
                                : 'border-gray-300 hover:border-green-400 bg-white'"
                            :title="task.status === 'completed' ? 'Mark as incomplete' : 'Mark as complete'"
                        >
                            <CheckCircle v-if="task.status === 'completed'" class="h-3 w-3" />
                        </button>

                        <!-- Task title -->
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-gray-900 truncate block">
                                {{ task.title }}
                            </span>
                            <div class="mt-1">
                                <TaskSizing :task="task" @updated="refreshTasks" />
                            </div>
                        </div>

                        <!-- Due date if exists -->
                        <div v-if="task.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                            <Calendar class="h-3 w-3" />
                            {{ new Date(task.due_date).toLocaleDateString() }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1">
                            <Button size="sm" variant="ghost" as-child class="h-8 w-8 p-0">
                                <Link v-if="project.id && task.id" :href="`/dashboard/projects/${project.id}/tasks/${task.id}/edit`">
                                    <Edit class="h-3 w-3" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Board View (Kanban) -->
                <div v-if="currentFilter === 'board'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- To Do Column -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <Circle class="h-4 w-4 text-gray-500" />
                            <h3 class="font-medium text-gray-900">To Do</h3>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
                                {{ tasks.filter(t => t.status === 'pending' && t.is_leaf).length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="pending"
                            ref="pendingColumnRef"
                            :style="{ minHeight: columnHeights.pending }"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'pending')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'pending')"
                            :class="{ 'bg-blue-100 border-2 border-blue-300 border-dashed rounded-lg': kanbanDragOverColumn === 'pending' }"
                        >
                            <div
                                v-for="task in tasks.filter(t => t.status === 'pending' && t.is_leaf)"
                                :key="task.id"
                                :data-task-id="task.id"
                                class="bg-white p-3 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move kanban-task"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                @dragend="onKanbanDragEnd"
                            >
                                <div class="flex items-start justify-between">
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">{{ task.title }}</h4>
                                </div>
                                <div class="mb-2">
                                    <TaskSizing :task="task" @updated="refreshTasks" />
                                </div>
                                <div v-if="task.description" class="text-xs text-gray-600 mb-2 line-clamp-2">
                                    {{ task.description }}
                                </div>
                                <div class="flex items-center justify-between">
                                    <component :is="getTaskTypeIcon(task)"
                                        :class="[
                                            'h-3 w-3',
                                            task.is_leaf ? 'text-green-600' : 'text-blue-600'
                                        ]" />
                                    <div v-if="task.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                                        <Calendar class="h-3 w-3" />
                                        {{ new Date(task.due_date).toLocaleDateString() }}
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state message -->
                            <div
                                v-if="tasks.filter(t => t.status === 'pending' && t.is_leaf).length === 0"
                                class="flex items-center justify-center h-48 text-gray-400 text-sm"
                            >
                                <div class="text-center">
                                    <div class="text-2xl mb-2">📋</div>
                                    <div>Drop tasks here</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <Clock class="h-4 w-4 text-blue-500" />
                            <h3 class="font-medium text-gray-900">In Progress</h3>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                                {{ tasks.filter(t => t.status === 'in_progress' && t.is_leaf).length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="in_progress"
                            ref="inProgressColumnRef"
                            :style="{ minHeight: columnHeights.in_progress }"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'in_progress')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'in_progress')"
                            :class="{ 'bg-blue-200 border-2 border-blue-400 border-dashed rounded-lg': kanbanDragOverColumn === 'in_progress' }"
                        >
                            <div
                                v-for="task in tasks.filter(t => t.status === 'in_progress' && t.is_leaf)"
                                :key="task.id"
                                :data-task-id="task.id"
                                class="bg-white p-3 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move kanban-task"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                @dragend="onKanbanDragEnd"
                            >
                                <div class="flex items-start justify-between">
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">{{ task.title }}</h4>
                                </div>
                                <div class="mb-2">
                                    <TaskSizing :task="task" @updated="refreshTasks" />
                                </div>
                                <div v-if="task.description" class="text-xs text-gray-600 mb-2 line-clamp-2">
                                    {{ task.description }}
                                </div>
                                <div class="flex items-center justify-between">
                                    <component :is="getTaskTypeIcon(task)"
                                        :class="[
                                            'h-3 w-3',
                                            task.is_leaf ? 'text-green-600' : 'text-blue-600'
                                        ]" />
                                    <div v-if="task.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                                        <Calendar class="h-3 w-3" />
                                        {{ new Date(task.due_date).toLocaleDateString() }}
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state message -->
                            <div
                                v-if="tasks.filter(t => t.status === 'in_progress' && t.is_leaf).length === 0"
                                class="flex items-center justify-center h-48 text-gray-400 text-sm"
                            >
                                <div class="text-center">
                                    <div class="text-2xl mb-2">⏳</div>
                                    <div>Drop tasks here</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Done Column -->
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <CheckCircle class="h-4 w-4 text-green-500" />
                            <h3 class="font-medium text-gray-900">Done</h3>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                {{ tasks.filter(t => t.status === 'completed' && t.is_leaf).length }}
                            </span>
                        </div>
                        <div
                            class="space-y-3 kanban-column"
                            data-status="completed"
                            ref="completedColumnRef"
                            :style="{ minHeight: columnHeights.completed }"
                            @dragover="onKanbanDragOver"
                            @dragenter="onKanbanDragEnter($event, 'completed')"
                            @dragleave="onKanbanDragLeave"
                            @drop="onKanbanDrop($event, 'completed')"
                            :class="{ 'bg-green-200 border-2 border-green-400 border-dashed rounded-lg': kanbanDragOverColumn === 'completed' }"
                        >
                            <div
                                v-for="task in tasks.filter(t => t.status === 'completed' && t.is_leaf)"
                                :key="task.id"
                                :data-task-id="task.id"
                                class="bg-white p-3 rounded-lg border shadow-sm hover:shadow-md transition-shadow cursor-move kanban-task opacity-75"
                                draggable="true"
                                @dragstart="onKanbanDragStart($event, task)"
                                @dragend="onKanbanDragEnd"
                            >
                                <div class="flex items-start justify-between">
                                    <h4 class="text-sm font-medium text-gray-900 mb-1 line-through">{{ task.title }}</h4>
                                </div>
                                <div class="mb-2">
                                    <TaskSizing :task="task" @updated="refreshTasks" />
                                </div>
                                <div v-if="task.description" class="text-xs text-gray-600 mb-2 line-clamp-2">
                                    {{ task.description }}
                                </div>
                                <div class="flex items-center justify-between">
                                    <component :is="getTaskTypeIcon(task)"
                                        :class="[
                                            'h-3 w-3',
                                            task.is_leaf ? 'text-green-600' : 'text-blue-600'
                                        ]" />
                                    <div v-if="task.due_date" class="flex items-center gap-1 text-xs text-gray-500">
                                        <Calendar class="h-3 w-3" />
                                        {{ new Date(task.due_date).toLocaleDateString() }}
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state message -->
                            <div
                                v-if="tasks.filter(t => t.status === 'completed' && t.is_leaf).length === 0"
                                class="flex items-center justify-center h-48 text-gray-400 text-sm"
                            >
                                <div class="text-center">
                                    <div class="text-2xl mb-2">✅</div>
                                    <div>Drop tasks here</div>
                                </div>
                            </div>
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
                            <span v-if="currentFilter !== 'top-level'">
                                Try switching to the List tab or create some tasks to get started.
                            </span>
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Button @click="changeFilter('top-level')" variant="outline" class="w-full">
                            View Task List
                        </Button>
                        <Button variant="default" class="w-full" as-child>
                            <Link v-if="project.id" :href="`/dashboard/projects/${project.id}/tasks/create`">
                                Create First Task
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            </div>

            <!-- Reordering Confirmation Dialog -->
            <Dialog v-model:open="showConfirmDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Task Reordering</DialogTitle>
                        <DialogDescription>
                            {{ confirmationData?.message }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="confirmationData" class="py-4">
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

/* Ensure draggable items have proper cursor */
.cursor-grab:hover .drag-handle {
    cursor: grab;
}

.cursor-grabbing .drag-handle {
    cursor: grabbing;
}

/* Hide scrollbar for mobile tab navigation */
.scrollbar-hide {
    -ms-overflow-style: none;  /* Internet Explorer 10+ */
    scrollbar-width: none;  /* Firefox */
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;  /* Safari and Chrome */
}

/* Mobile tab improvements */
@media (max-width: 640px) {
    .tab-navigation {
        scroll-behavior: smooth;
    }

    .tab-button {
        min-width: 60px;
        flex-shrink: 0;
    }
}
</style>
