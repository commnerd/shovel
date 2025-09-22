<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Calendar, Clock, Target, Users, Plus, Edit, MoreHorizontal } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Iteration {
    id: number;
    name: string;
    description?: string;
    start_date: string;
    end_date: string;
    status: 'planned' | 'active' | 'completed' | 'cancelled';
    capacity_points?: number;
    committed_points: number;
    completed_points: number;
    sort_order: number;
    goals?: string[];
    tasks_count?: number;
}

interface Project {
    id: number;
    title: string;
    project_type: 'finite' | 'iterative';
    default_iteration_length_weeks?: number;
    auto_create_iterations: boolean;
}

interface Props {
    project: Project;
    iterations: Iteration[];
    currentIteration?: Iteration;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    refresh: []
}>();

const showCreateForm = ref(false);
const editingIteration = ref<Iteration | null>(null);

// Computed properties
const plannedIterations = computed(() =>
    props.iterations.filter(i => i.status === 'planned').sort((a, b) => a.sort_order - b.sort_order)
);

const activeIterations = computed(() =>
    props.iterations.filter(i => i.status === 'active').sort((a, b) => a.sort_order - b.sort_order)
);

const completedIterations = computed(() =>
    props.iterations.filter(i => i.status === 'completed').sort((a, b) => b.sort_order - a.sort_order)
);

const getIterationProgress = (iteration: Iteration) => {
    if (iteration.committed_points === 0) return 0;
    return Math.round((iteration.completed_points / iteration.committed_points) * 100);
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'completed': return 'bg-blue-100 text-blue-800';
        case 'planned': return 'bg-yellow-100 text-yellow-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getDaysRemaining = (endDate: string) => {
    const end = new Date(endDate);
    const today = new Date();
    const diffTime = end.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
};

const isOverdue = (iteration: Iteration) => {
    return iteration.status !== 'completed' && getDaysRemaining(iteration.end_date) < 0;
};

const createIteration = () => {
    if (!props.project.id) {
        console.error('Project ID is undefined, cannot create iteration');
        return;
    }

    router.post(`/dashboard/projects/${props.project.id}/iterations`, {
        name: `Sprint ${props.iterations.length + 1}`,
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date(Date.now() + (props.project.default_iteration_length_weeks || 2) * 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        status: 'planned',
        capacity_points: 40,
    }, {
        onSuccess: () => {
            showCreateForm.value = false;
            emit('refresh');
        }
    });
};

const startIteration = (iteration: Iteration) => {
    if (!props.project.id || !iteration.id) {
        console.error('Project ID or iteration ID is undefined, cannot start iteration');
        return;
    }

    router.put(`/dashboard/projects/${props.project.id}/iterations/${iteration.id}`, {
        status: 'active'
    }, {
        onSuccess: () => emit('refresh')
    });
};

const completeIteration = (iteration: Iteration) => {
    if (!props.project.id || !iteration.id) {
        console.error('Project ID or iteration ID is undefined, cannot complete iteration');
        return;
    }

    router.put(`/dashboard/projects/${props.project.id}/iterations/${iteration.id}`, {
        status: 'completed'
    }, {
        onSuccess: () => emit('refresh')
    });
};

const cancelIteration = (iteration: Iteration) => {
    if (!props.project.id || !iteration.id) {
        console.error('Project ID or iteration ID is undefined, cannot cancel iteration');
        return;
    }

    router.put(`/dashboard/projects/${props.project.id}/iterations/${iteration.id}`, {
        status: 'cancelled'
    }, {
        onSuccess: () => emit('refresh')
    });
};

const deleteIteration = (iteration: Iteration) => {
    if (!props.project.id || !iteration.id) {
        console.error('Project ID or iteration ID is undefined, cannot delete iteration');
        return;
    }

    if (confirm(`Are you sure you want to delete "${iteration.name}"? This action cannot be undone.`)) {
        router.delete(`/dashboard/projects/${props.project.id}/iterations/${iteration.id}`, {
            onSuccess: () => emit('refresh')
        });
    }
};

const viewIteration = (iteration: Iteration) => {
    if (!props.project.id || !iteration.id) {
        console.error('Project ID or iteration ID is undefined, cannot view iteration');
        return;
    }

    router.visit(`/dashboard/projects/${props.project.id}/iterations/${iteration.id}`);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Sprint Management</h2>
                <p class="text-gray-600">Manage iterations and track progress for {{ project.title }}</p>
            </div>
            <Button
                v-if="project.project_type === 'iterative'"
                @click="showCreateForm = true"
                class="flex items-center gap-2"
            >
                <Plus class="h-4 w-4" />
                Create Sprint
            </Button>
        </div>

        <!-- Quick Create Form -->
        <Card v-if="showCreateForm" class="border-blue-200 bg-blue-50">
            <CardHeader>
                <CardTitle class="text-blue-900">Create New Sprint</CardTitle>
                <CardDescription>Set up a new iteration for your project</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sprint Name</label>
                        <input
                            type="text"
                            :value="`Sprint ${iterations.length + 1}`"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            readonly
                        />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (Story Points)</label>
                        <input
                            type="number"
                            value="40"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input
                            type="date"
                            :value="new Date().toISOString().split('T')[0]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input
                            type="date"
                            :value="new Date(Date.now() + (project.default_iteration_length_weeks || 2) * 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button @click="createIteration" class="bg-blue-600 hover:bg-blue-700">
                        Create Sprint
                    </Button>
                    <Button variant="outline" @click="showCreateForm = false">
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Active Iterations -->
        <div v-if="activeIterations.length > 0" class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <Target class="h-5 w-5 text-green-600" />
                Active Sprints
            </h3>
            <div class="grid gap-4">
                <Card
                    v-for="iteration in activeIterations"
                    :key="iteration.id"
                    class="border-green-200 hover:shadow-md transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <CardTitle class="text-lg">{{ iteration.name }}</CardTitle>
                                <Badge :class="getStatusColor(iteration.status)">
                                    {{ iteration.status.charAt(0).toUpperCase() + iteration.status.slice(1) }}
                                </Badge>
                                <Badge v-if="isOverdue(iteration)" variant="destructive">
                                    Overdue
                                </Badge>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem @click="viewIteration(iteration)">
                                        View Details
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="completeIteration(iteration)">
                                        Complete Sprint
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="cancelIteration(iteration)" class="text-red-600">
                                        Cancel Sprint
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <CardDescription class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1">
                                <Calendar class="h-4 w-4" />
                                {{ new Date(iteration.start_date).toLocaleDateString() }} - {{ new Date(iteration.end_date).toLocaleDateString() }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Clock class="h-4 w-4" />
                                {{ getDaysRemaining(iteration.end_date) }} days remaining
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <!-- Progress Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Progress</span>
                                    <span>{{ getIterationProgress(iteration) }}%</span>
                                </div>
                                <Progress :value="getIterationProgress(iteration)" class="h-2" />
                            </div>

                            <!-- Points Summary -->
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div class="text-center">
                                    <div class="font-semibold">{{ iteration.committed_points }}</div>
                                    <div class="text-gray-600">Committed</div>
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold text-green-600">{{ iteration.completed_points }}</div>
                                    <div class="text-gray-600">Completed</div>
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold">{{ iteration.capacity_points || '∞' }}</div>
                                    <div class="text-gray-600">Capacity</div>
                                </div>
                            </div>

                            <!-- Goals -->
                            <div v-if="iteration.goals && iteration.goals.length > 0">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Goals</h4>
                                <ul class="space-y-1">
                                    <li v-for="goal in iteration.goals" :key="goal" class="text-sm text-gray-600 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                        {{ goal }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Planned Iterations -->
        <div v-if="plannedIterations.length > 0" class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <Clock class="h-5 w-5 text-yellow-600" />
                Planned Sprints
            </h3>
            <div class="grid gap-4">
                <Card
                    v-for="iteration in plannedIterations"
                    :key="iteration.id"
                    class="border-yellow-200 hover:shadow-md transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <CardTitle class="text-lg">{{ iteration.name }}</CardTitle>
                                <Badge :class="getStatusColor(iteration.status)">
                                    {{ iteration.status.charAt(0).toUpperCase() + iteration.status.slice(1) }}
                                </Badge>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem @click="viewIteration(iteration)">
                                        View Details
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="startIteration(iteration)">
                                        Start Sprint
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="deleteIteration(iteration)" class="text-red-600">
                                        Delete Sprint
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <CardDescription class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1">
                                <Calendar class="h-4 w-4" />
                                {{ new Date(iteration.start_date).toLocaleDateString() }} - {{ new Date(iteration.end_date).toLocaleDateString() }}
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="text-center">
                                <div class="font-semibold">{{ iteration.committed_points }}</div>
                                <div class="text-gray-600">Committed</div>
                            </div>
                            <div class="text-center">
                                <div class="font-semibold">{{ iteration.capacity_points || '∞' }}</div>
                                <div class="text-gray-600">Capacity</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Completed Iterations -->
        <div v-if="completedIterations.length > 0" class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <Users class="h-5 w-5 text-blue-600" />
                Completed Sprints
            </h3>
            <div class="grid gap-4">
                <Card
                    v-for="iteration in completedIterations"
                    :key="iteration.id"
                    class="border-blue-200 hover:shadow-md transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <CardTitle class="text-lg">{{ iteration.name }}</CardTitle>
                                <Badge :class="getStatusColor(iteration.status)">
                                    {{ iteration.status.charAt(0).toUpperCase() + iteration.status.slice(1) }}
                                </Badge>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem @click="viewIteration(iteration)">
                                        View Details
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <CardDescription class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1">
                                <Calendar class="h-4 w-4" />
                                {{ new Date(iteration.start_date).toLocaleDateString() }} - {{ new Date(iteration.end_date).toLocaleDateString() }}
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <!-- Progress Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Final Progress</span>
                                    <span>{{ getIterationProgress(iteration) }}%</span>
                                </div>
                                <Progress :value="getIterationProgress(iteration)" class="h-2" />
                            </div>

                            <!-- Points Summary -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="text-center">
                                    <div class="font-semibold">{{ iteration.committed_points }}</div>
                                    <div class="text-gray-600">Committed</div>
                                </div>
                                <div class="text-center">
                                    <div class="font-semibold text-green-600">{{ iteration.completed_points }}</div>
                                    <div class="text-gray-600">Completed</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Empty State -->
        <Card v-if="iterations.length === 0" class="border-dashed border-2 border-gray-300">
            <CardContent class="text-center py-12">
                <Target class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Sprints Yet</h3>
                <p class="text-gray-600 mb-4">Create your first sprint to start managing iterations for this project.</p>
                <Button v-if="project.project_type === 'iterative'" @click="showCreateForm = true">
                    <Plus class="h-4 w-4 mr-2" />
                    Create First Sprint
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
