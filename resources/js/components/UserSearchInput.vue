<template>
    <div class="relative w-full">
        <!-- Search Input -->
        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <Input
                ref="searchInput"
                v-model="searchQuery"
                type="text"
                :placeholder="placeholder"
                class="pl-10 pr-4"
                @input="handleInput"
                @focus="showDropdown = true"
                @blur="handleBlur"
                @keydown="handleKeydown"
                autocomplete="off"
            />
            <Button
                v-if="searchQuery"
                variant="ghost"
                size="sm"
                class="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2 p-0"
                @click="clearSearch"
            >
                <X class="h-3 w-3" />
            </Button>
        </div>

        <!-- Loading Indicator -->
        <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
            <Loader class="h-4 w-4 animate-spin text-gray-400" />
        </div>

        <!-- Dropdown Results -->
        <div
            v-if="showDropdown && (searchResults.length > 0 || searchQuery.length >= minChars)"
            class="absolute z-50 mt-1 w-full rounded-md border bg-white shadow-lg"
            @mousedown.prevent
        >
            <div class="max-h-64 overflow-y-auto">
                <!-- No Results -->
                <div
                    v-if="searchQuery.length >= minChars && searchResults.length === 0 && !isLoading"
                    class="px-4 py-3 text-sm text-gray-500"
                >
                    No users found for "{{ searchQuery }}"
                </div>

                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="px-4 py-3 text-sm text-gray-500 flex items-center gap-2"
                >
                    <Loader class="h-3 w-3 animate-spin" />
                    Searching users...
                </div>

                <!-- Search Results -->
                <div
                    v-for="(user, index) in searchResults"
                    :key="user.id"
                    :class="[
                        'cursor-pointer px-4 py-3 hover:bg-gray-50',
                        selectedIndex === index && 'bg-blue-50'
                    ]"
                    @click="selectUser(user)"
                    @mouseenter="selectedIndex = index"
                >
                    <div class="flex items-center gap-3">
                        <!-- Avatar -->
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="text-sm font-medium text-blue-600">{{ user.avatar }}</span>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>

                                <!-- Status Badges -->
                                <Crown v-if="user.is_super_admin" class="h-3 w-3 text-yellow-500" title="Super Admin" />
                                <Shield v-else-if="user.is_admin" class="h-3 w-3 text-blue-500" title="Admin" />

                                <Badge v-if="user.pending_approval" variant="outline" class="bg-orange-50 text-orange-700 border-orange-300 text-xs">
                                    Pending
                                </Badge>
                            </div>
                            <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ user.organization_name }}</p>
                        </div>

                        <!-- Action Indicator -->
                        <div class="flex-shrink-0">
                            <ChevronRight class="h-4 w-4 text-gray-400" />
                        </div>
                    </div>
                </div>

                <!-- Show More Results -->
                <div
                    v-if="searchResults.length >= maxResults"
                    class="border-t px-4 py-2 text-xs text-gray-500 bg-gray-50"
                >
                    Showing first {{ maxResults }} results. Refine your search for more specific results.
                </div>
            </div>
        </div>

        <!-- Minimum Characters Notice -->
        <div
            v-if="showDropdown && searchQuery.length > 0 && searchQuery.length < minChars"
            class="absolute z-50 mt-1 w-full rounded-md border bg-white shadow-lg"
        >
            <div class="px-4 py-3 text-sm text-gray-500">
                Type at least {{ minChars }} characters to search
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, X, Loader, Crown, Shield, ChevronRight } from 'lucide-vue-next';

interface UserSearchResult {
    id: number;
    name: string;
    email: string;
    organization_name: string;
    organization_id: number;
    is_admin: boolean;
    is_super_admin: boolean;
    pending_approval: boolean;
    avatar: string;
}

interface Props {
    placeholder?: string;
    minChars?: number;
    maxResults?: number;
    debounceMs?: number;
    searchUrl?: string;
}

interface Emits {
    (e: 'select', user: UserSearchResult): void;
    (e: 'search', query: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search users by name or email...',
    minChars: 2,
    maxResults: 10,
    debounceMs: 300,
    searchUrl: '/super-admin/users/search',
});

const emit = defineEmits<Emits>();

// Reactive state
const searchInput = ref<HTMLInputElement>();
const searchQuery = ref('');
const searchResults = ref<UserSearchResult[]>([]);
const showDropdown = ref(false);
const isLoading = ref(false);
const selectedIndex = ref(-1);
const debounceTimer = ref<NodeJS.Timeout>();

// Methods
const handleInput = () => {
    emit('search', searchQuery.value);

    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }

    if (searchQuery.value.length < props.minChars) {
        searchResults.value = [];
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    selectedIndex.value = -1;

    debounceTimer.value = setTimeout(async () => {
        await performSearch();
    }, props.debounceMs);
};

const performSearch = async () => {
    if (searchQuery.value.length < props.minChars) {
        isLoading.value = false;
        return;
    }

    try {
        const response = await fetch(`${props.searchUrl}?query=${encodeURIComponent(searchQuery.value)}&limit=${props.maxResults}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Search failed');
        }

        const data = await response.json();
        searchResults.value = data.users || [];
    } catch (error) {
        console.error('User search error:', error);
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
};

const selectUser = (user: UserSearchResult) => {
    emit('select', user);
    searchQuery.value = user.name;
    showDropdown.value = false;
    selectedIndex.value = -1;
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
    showDropdown.value = false;
    selectedIndex.value = -1;
    searchInput.value?.focus();
};

const handleBlur = () => {
    // Delay hiding dropdown to allow for click events
    setTimeout(() => {
        showDropdown.value = false;
    }, 150);
};

const handleKeydown = (event: KeyboardEvent) => {
    if (!showDropdown.value || searchResults.value.length === 0) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedIndex.value = Math.min(selectedIndex.value + 1, searchResults.value.length - 1);
            break;

        case 'ArrowUp':
            event.preventDefault();
            selectedIndex.value = Math.max(selectedIndex.value - 1, -1);
            break;

        case 'Enter':
            event.preventDefault();
            if (selectedIndex.value >= 0 && selectedIndex.value < searchResults.value.length) {
                selectUser(searchResults.value[selectedIndex.value]);
            }
            break;

        case 'Escape':
            event.preventDefault();
            showDropdown.value = false;
            selectedIndex.value = -1;
            break;
    }
};

// Focus method for external use
const focus = () => {
    searchInput.value?.focus();
};

// Cleanup
onUnmounted(() => {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
});

// Expose methods
defineExpose({
    focus,
    clearSearch,
});
</script>

<style scoped>
/* Ensure dropdown appears above other elements */
.z-50 {
    z-index: 50;
}

/* Smooth transitions */
.transition-all {
    transition: all 0.2s ease-in-out;
}

/* Animation for the loader */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
