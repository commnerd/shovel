<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Shirt, Calculator, Edit3, Check, X } from 'lucide-vue-next';

interface Task {
    id: number;
    title: string;
    parent_id?: number;
    depth: number;
    size?: 'xs' | 's' | 'm' | 'l' | 'xl';
    initial_story_points?: number;
    current_story_points?: number;
    story_points_change_count?: number;
}

interface Props {
    task: Task;
    canEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canEdit: true,
});

const emit = defineEmits<{
    updated: []
}>();

const isEditingSize = ref(false);
const isEditingPoints = ref(false);
const editingSize = ref(props.task.size || '');
const editingPoints = ref(props.task.current_story_points || '');

// T-shirt size options
const sizeOptions = [
    { value: 'xs', label: 'XS', description: 'Extra Small (1-2 days)' },
    { value: 's', label: 'S', description: 'Small (3-5 days)' },
    { value: 'm', label: 'M', description: 'Medium (1-2 weeks)' },
    { value: 'l', label: 'L', description: 'Large (2-4 weeks)' },
    { value: 'xl', label: 'XL', description: 'Extra Large (1+ months)' },
];

// Fibonacci story points
const fibonacciPoints = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

// Computed properties
const isTopLevel = computed(() => props.task.depth === 0);
const isSubtask = computed(() => props.task.depth > 0);
const canHaveSize = computed(() => {
    // Only top-level tasks can have T-shirt sizes
    return isTopLevel.value && props.task.depth === 0;
});
const canHaveStoryPoints = computed(() => {
    // Only subtasks can have story points
    return isSubtask.value && props.task.depth > 0;
});

const getSizeDisplayName = (size: string) => {
    const option = sizeOptions.find(opt => opt.value === size);
    return option ? option.label : size.toUpperCase();
};

const getSizeDescription = (size: string) => {
    const option = sizeOptions.find(opt => opt.value === size);
    return option ? option.description : '';
};

const getSizeColor = (size: string) => {
    switch (size) {
        case 'xs': return 'bg-gray-100 text-gray-800';
        case 's': return 'bg-green-100 text-green-800';
        case 'm': return 'bg-yellow-100 text-yellow-800';
        case 'l': return 'bg-orange-100 text-orange-800';
        case 'xl': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPointsColor = (points: number) => {
    if (points <= 3) return 'bg-green-100 text-green-800';
    if (points <= 8) return 'bg-yellow-100 text-yellow-800';
    if (points <= 21) return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
};

const hasSizeChanged = computed(() => {
    return props.task.size && props.task.size !== editingSize.value;
});

const hasPointsChanged = computed(() => {
    return props.task.current_story_points && props.task.current_story_points !== Number(editingPoints.value);
});

const startEditingSize = () => {
    if (!canHaveSize.value) return;
    editingSize.value = props.task.size || '';
    isEditingSize.value = true;
};

const startEditingPoints = () => {
    if (!canHaveStoryPoints.value) return;
    editingPoints.value = props.task.current_story_points || '';
    isEditingPoints.value = true;
};

const saveSize = () => {
    if (!canHaveSize.value || !editingSize.value) return;

    router.patch(`/dashboard/tasks/${props.task.id}`, {
        size: editingSize.value,
    }, {
        onSuccess: () => {
            isEditingSize.value = false;
            emit('updated');
        },
        onError: (errors) => {
            console.error('Failed to update task size:', errors);
        }
    });
};

const savePoints = () => {
    if (!canHaveStoryPoints.value || !editingPoints.value) return;

    const points = Number(editingPoints.value);
    if (!fibonacciPoints.includes(points)) {
        alert('Story points must be a Fibonacci number (1, 2, 3, 5, 8, 13, 21, 34, 55, 89)');
        return;
    }

    router.patch(`/dashboard/tasks/${props.task.id}`, {
        current_story_points: points,
    }, {
        onSuccess: () => {
            isEditingPoints.value = false;
            emit('updated');
        },
        onError: (errors) => {
            console.error('Failed to update story points:', errors);
        }
    });
};

const cancelEditingSize = () => {
    editingSize.value = props.task.size || '';
    isEditingSize.value = false;
};

const cancelEditingPoints = () => {
    editingPoints.value = props.task.current_story_points || '';
    isEditingPoints.value = false;
};
</script>

<template>
    <div class="flex items-center gap-2">
        <!-- T-shirt Size (Top-level tasks only) -->
        <div v-if="canHaveSize && !canHaveStoryPoints" class="flex items-center gap-2">
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger>
                        <div
                            v-if="!isEditingSize && props.canEdit"
                            @click="startEditingSize"
                            class="cursor-pointer"
                        >
                            <Badge v-if="task.size" :class="getSizeColor(task.size)" class="flex items-center gap-1">
                                <Shirt class="h-3 w-3" />
                                {{ getSizeDisplayName(task.size) }}
                            </Badge>
                            <Badge v-else variant="outline" class="flex items-center gap-1 hover:bg-gray-50">
                                <Shirt class="h-3 w-3" />
                                Set Size
                            </Badge>
                        </div>
                        <Badge v-else-if="task.size" :class="getSizeColor(task.size)" class="flex items-center gap-1">
                            <Shirt class="h-3 w-3" />
                            {{ getSizeDisplayName(task.size) }}
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent v-if="task.size">
                        <p>{{ getSizeDescription(task.size) }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <!-- Size Editing -->
            <div v-if="isEditingSize" class="flex items-center gap-1">
                <Select v-model="editingSize">
                    <SelectTrigger class="w-20 h-6 text-xs">
                        <SelectValue placeholder="Size" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in sizeOptions" :key="option.value" :value="option.value">
                            {{ option.label }} - {{ option.description }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button size="sm" variant="ghost" @click="saveSize" :disabled="!editingSize">
                    <Check class="h-3 w-3 text-green-600" />
                </Button>
                <Button size="sm" variant="ghost" @click="cancelEditingSize">
                    <X class="h-3 w-3 text-red-600" />
                </Button>
            </div>
        </div>

        <!-- Story Points (Subtasks only) -->
        <div v-if="canHaveStoryPoints && !canHaveSize" class="flex items-center gap-2">
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger>
                        <div
                            v-if="!isEditingPoints && props.canEdit"
                            @click="startEditingPoints"
                            class="cursor-pointer"
                        >
                            <Badge v-if="task.current_story_points" :class="getPointsColor(task.current_story_points)" class="flex items-center gap-1">
                                <Calculator class="h-3 w-3" />
                                {{ task.current_story_points }} pts
                            </Badge>
                            <Badge v-else variant="outline" class="flex items-center gap-1 hover:bg-gray-50">
                                <Calculator class="h-3 w-3" />
                                Set Points
                            </Badge>
                        </div>
                        <Badge v-else-if="task.current_story_points" :class="getPointsColor(task.current_story_points)" class="flex items-center gap-1">
                            <Calculator class="h-3 w-3" />
                            {{ task.current_story_points }} pts
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent v-if="task.current_story_points">
                        <div class="space-y-1">
                            <p>Story Points: {{ task.current_story_points }}</p>
                            <p v-if="task.initial_story_points && task.initial_story_points !== task.current_story_points">
                                Originally: {{ task.initial_story_points }} pts
                            </p>
                            <p v-if="task.story_points_change_count && task.story_points_change_count > 0">
                                Changed {{ task.story_points_change_count }} time{{ task.story_points_change_count > 1 ? 's' : '' }}
                            </p>
                        </div>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <!-- Points Editing -->
            <div v-if="isEditingPoints" class="flex items-center gap-1">
                <Select v-model="editingPoints">
                    <SelectTrigger class="w-20 h-6 text-xs">
                        <SelectValue placeholder="Pts" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="points in fibonacciPoints" :key="points" :value="points.toString()">
                            {{ points }} pts
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button size="sm" variant="ghost" @click="savePoints" :disabled="!editingPoints">
                    <Check class="h-3 w-3 text-green-600" />
                </Button>
                <Button size="sm" variant="ghost" @click="cancelEditingPoints">
                    <X class="h-3 w-3 text-red-600" />
                </Button>
            </div>
        </div>
    </div>
</template>
