<template>
  <AppLayout title="Today's Tasks">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Today's Tasks
          </h2>
          <p class="text-sm text-gray-600 mt-1">
            AI-curated task recommendations for {{ new Date().toLocaleDateString() }}
            <span v-if="cacheTimestamp" class="text-xs text-gray-400 ml-2">
              (Last updated: {{ formatTime(cacheTimestamp) }})
            </span>
          </p>
        </div>
        <div class="flex items-center space-x-3">
          <button
            @click="refreshCurations"
            :disabled="isRefreshing"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
          >
            <svg v-if="isRefreshing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-500">Curations</div>
                  <div class="text-2xl font-bold text-gray-900">{{ stats.total_curations }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-500">Suggestions</div>
                  <div class="text-2xl font-bold text-gray-900">{{ stats.total_suggestions }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-500">Priority Tasks</div>
                  <div class="text-2xl font-bold text-gray-900">{{ stats.priority_tasks }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-500">Overdue</div>
                  <div class="text-2xl font-bold text-gray-900">{{ stats.overdue_tasks }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Priority Tasks Section -->
        <div v-if="priorityTasks.length > 0" class="mb-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">
                🎯 Priority Tasks (Due Today or In Progress)
              </h3>
              <div class="space-y-3">
                <div
                  v-for="task in priorityTasks"
                  :key="task.id"
                  class="flex items-center justify-between p-4 border rounded-lg"
                  :class="{
                    'border-red-200 bg-red-50': task.is_overdue,
                    'border-yellow-200 bg-yellow-50': !task.is_overdue && task.days_until_due <= 1,
                    'border-blue-200 bg-blue-50': task.status === 'in_progress',
                    'border-gray-200': !task.is_overdue && task.days_until_due > 1 && task.status !== 'in_progress'
                  }"
                >
                  <div class="flex-1">
                    <div class="flex items-center space-x-3">
                      <h4 class="font-medium text-gray-900">{{ task.title }}</h4>
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                          'bg-gray-100 text-gray-800': task.status === 'pending',
                          'bg-blue-100 text-blue-800': task.status === 'in_progress',
                          'bg-green-100 text-green-800': task.status === 'completed'
                        }"
                      >
                        {{ task.status.replace('_', ' ').toUpperCase() }}
                      </span>
                      <span v-if="task.is_overdue" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        OVERDUE
                      </span>
                      <span v-else-if="task.days_until_due === 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        DUE TODAY
                      </span>
                      <span v-else-if="task.days_until_due === 1" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        DUE TOMORROW
                      </span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{{ task.project.title }}</p>
                    <p v-if="task.description" class="text-sm text-gray-500 mt-1">{{ task.description }}</p>
                  </div>
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="task.status !== 'in_progress'"
                      @click="updateTaskStatus(task.id, 'in_progress')"
                      class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                    >
                      Start
                    </button>
                    <button
                      v-if="task.status !== 'completed'"
                      @click="completeTask(task.id)"
                      class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                    >
                      Complete
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- AI Curations Section -->
        <div v-if="curations.length > 0">
          <div class="space-y-6">
            <div
              v-for="curation in curations"
              :key="curation.id"
              class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
            >
              <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center space-x-3">
                    <h3 class="text-lg font-medium text-gray-900">
                      🤖 {{ curation.project.title }}
                    </h3>
                    <span v-if="curation.is_new" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      NEW
                    </span>
                    <span v-if="curation.ai_generated" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                      AI Generated
                    </span>
                  </div>
                  <button
                    @click="dismissCuration(curation.id)"
                    class="text-gray-400 hover:text-gray-600"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                  </button>
                </div>

                <!-- Summary -->
                <div v-if="curation.summary" class="mb-4">
                  <p class="text-gray-700">{{ curation.summary }}</p>
                </div>

                <!-- Focus Areas -->
                <div v-if="curation.focus_areas && curation.focus_areas.length > 0" class="mb-4">
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="area in curation.focus_areas"
                      :key="area"
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                    >
                      {{ area.replace('_', ' ').toUpperCase() }}
                    </span>
                  </div>
                </div>

                <!-- Suggestions -->
                <div v-if="curation.suggestions.length > 0" class="space-y-3">
                  <div
                    v-for="suggestion in curation.suggestions"
                    :key="suggestion.task_id || suggestion.message"
                    class="flex items-start space-x-3 p-3 rounded-lg"
                    :class="{
                      'bg-red-50 border border-red-200': suggestion.type === 'risk',
                      'bg-yellow-50 border border-yellow-200': suggestion.type === 'priority',
                      'bg-blue-50 border border-blue-200': suggestion.type === 'optimization',
                      'bg-gray-50 border border-gray-200': !['risk', 'priority', 'optimization'].includes(suggestion.type)
                    }"
                  >
                    <div class="flex-shrink-0 mt-0.5">
                      <div
                        class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium"
                        :class="{
                          'bg-red-100 text-red-600': suggestion.type === 'risk',
                          'bg-yellow-100 text-yellow-600': suggestion.type === 'priority',
                          'bg-blue-100 text-blue-600': suggestion.type === 'optimization',
                          'bg-gray-100 text-gray-600': !['risk', 'priority', 'optimization'].includes(suggestion.type)
                        }"
                      >
                        {{ suggestion.type === 'risk' ? '⚠️' : suggestion.type === 'priority' ? '⭐' : suggestion.type === 'optimization' ? '💡' : '📝' }}
                      </div>
                    </div>
                    <div class="flex-1">
                      <div v-if="suggestion.task_id && tasks[suggestion.task_id]" class="mb-2">
                        <h4 class="font-medium text-gray-900">{{ tasks[suggestion.task_id].title }}</h4>
                        <p class="text-sm text-gray-600">{{ tasks[suggestion.task_id].project.title }}</p>
                      </div>
                      <p class="text-sm text-gray-700">{{ suggestion.message }}</p>

                      <!-- Task Actions -->
                      <div v-if="suggestion.task_id && tasks[suggestion.task_id]" class="mt-2 flex space-x-2">
                        <button
                          v-if="tasks[suggestion.task_id].status !== 'in_progress'"
                          @click="updateTaskStatus(suggestion.task_id, 'in_progress')"
                          class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                        >
                          Start Task
                        </button>
                        <button
                          v-if="tasks[suggestion.task_id].status !== 'completed'"
                          @click="completeTask(suggestion.task_id)"
                          class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                        >
                          Mark Complete
                        </button>
                        <Link
                          :href="`/dashboard/projects/${tasks[suggestion.task_id].project.id}/tasks`"
                          class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
                        >
                          View Project
                        </Link>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="curations.length === 0 && priorityTasks.length === 0" class="text-center py-12">
          <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Tasks for Today</h3>
            <p class="text-gray-600 mb-4">
              You don't have any curated tasks for today yet. This could mean:
            </p>
            <ul class="text-sm text-gray-500 text-left space-y-1 mb-6">
              <li>• No active projects with pending tasks</li>
              <li>• Daily curation hasn't run yet (runs at 3:00 AM)</li>
              <li>• All your tasks are up to date!</li>
            </ul>
            <button
              @click="refreshCurations"
              :disabled="isRefreshing"
              class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
            >
              {{ isRefreshing ? 'Generating...' : 'Generate Today\'s Tasks' }}
            </button>
          </div>
        </div>

        <!-- No Priority Tasks but Has Curations -->
        <div v-else-if="priorityTasks.length === 0 && curations.length > 0" class="mb-8">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-green-800">Great! You don't have any overdue or urgent tasks today.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

interface Task {
  id: number
  title: string
  description: string
  status: 'pending' | 'in_progress' | 'completed'
  due_date: string | null
  size: string | null
  current_story_points: number | null
  project: {
    id: number
    title: string
    project_type: 'finite' | 'iterative'
  }
  parent: {
    id: number
    title: string
  } | null
  is_overdue?: boolean
  days_until_due?: number | null
}

interface Suggestion {
  type: 'priority' | 'risk' | 'optimization' | string
  task_id?: number
  message: string
}

interface Curation {
  id: number
  project: {
    id: number
    title: string
    project_type: 'finite' | 'iterative'
  }
  suggestions: Suggestion[]
  summary: string | null
  focus_areas: string[]
  ai_generated: boolean
  ai_provider: string | null
  is_new: boolean
  created_at: string
}

interface Stats {
  total_curations: number
  total_suggestions: number
  priority_tasks: number
  overdue_tasks: number
}

interface Props {
  curations: Curation[]
  tasks: Record<number, Task>
  priorityTasks: Task[]
  activeProjects: Array<{
    id: number
    title: string
    project_type: 'finite' | 'iterative'
    due_date: string | null
  }>
  stats: Stats
  cache_timestamp?: string
}

const props = defineProps<Props>()

const isRefreshing = ref(false)

const cacheTimestamp = computed(() => props.cache_timestamp)

const formatTime = (timestamp: string) => {
  return new Date(timestamp).toLocaleTimeString()
}

const refreshCurations = async () => {
  isRefreshing.value = true

  try {
    // Add cache busting timestamp
    const timestamp = Date.now()
    const response = await fetch(`/dashboard/todays-tasks/refresh?t=${timestamp}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0',
      },
    })

    if (response.ok) {
      // Reload the page to show fresh curations with cache busting
      router.reload({ only: ['curations', 'tasks', 'priorityTasks', 'stats'] })
    } else {
      alert('Failed to refresh curations. Please try again.')
    }
  } catch (error) {
    console.error('Error refreshing curations:', error)
    alert('An error occurred while refreshing curations.')
  } finally {
    isRefreshing.value = false
  }
}

const dismissCuration = async (curationId: number) => {
  try {
    const response = await fetch(`/dashboard/todays-tasks/curations/${curationId}/dismiss`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (response.ok) {
      // Remove the curation from the display
      router.reload()
    } else {
      alert('Failed to dismiss curation.')
    }
  } catch (error) {
    console.error('Error dismissing curation:', error)
    alert('An error occurred while dismissing the curation.')
  }
}

const updateTaskStatus = async (taskId: number, status: string) => {
  try {
    const response = await fetch(`/dashboard/todays-tasks/tasks/${taskId}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ status }),
    })

    if (response.ok) {
      // Reload to show updated status
      router.reload()
    } else {
      alert('Failed to update task status.')
    }
  } catch (error) {
    console.error('Error updating task status:', error)
    alert('An error occurred while updating the task.')
  }
}

const completeTask = async (taskId: number) => {
  try {
    const response = await fetch(`/dashboard/todays-tasks/tasks/${taskId}/complete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (response.ok) {
      // Reload to show updated status
      router.reload()
    } else {
      alert('Failed to complete task.')
    }
  } catch (error) {
    console.error('Error completing task:', error)
    alert('An error occurred while completing the task.')
  }
}
</script>
