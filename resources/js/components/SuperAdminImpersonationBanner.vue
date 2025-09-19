<template>
    <!-- Super Admin or Admin Impersonation Banner -->
    <div
        v-if="$page.props.auth?.original_super_admin_id || $page.props.auth?.original_admin_id"
        class="fixed bottom-4 right-4 z-50 max-w-sm"
        role="alert"
        aria-live="polite"
    >
        <Card class="border-yellow-200 bg-yellow-50 shadow-lg">
            <CardContent class="p-4">
                <div class="flex items-center gap-3">
                    <!-- Icon -->
                    <div class="flex-shrink-0">
                        <Crown class="h-5 w-5 text-yellow-600" />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-yellow-800">
                            {{ $page.props.auth?.original_super_admin_id ? 'Super Admin Mode' : 'Admin Mode' }}
                        </p>
                        <p class="text-xs text-yellow-700 truncate">
                            Viewing as {{ $page.props.auth.user?.name }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="returnToSuperAdmin"
                            :disabled="isReturning"
                            class="border-yellow-300 text-yellow-700 hover:bg-yellow-100 text-xs px-2 py-1"
                        >
                            <ArrowLeft v-if="!isReturning" class="h-3 w-3 mr-1" />
                            <Loader v-else class="h-3 w-3 mr-1 animate-spin" />
                            {{ isReturning ? 'Returning...' : 'Return' }}
                        </Button>

                        <!-- Minimize Button -->
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="toggleMinimized"
                            class="text-yellow-600 hover:bg-yellow-100 text-xs px-1 py-0 h-5"
                            :title="isMinimized ? 'Expand' : 'Minimize'"
                        >
                            <ChevronDown v-if="!isMinimized" class="h-3 w-3" />
                            <ChevronUp v-else class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Minimized Version -->
    <div
        v-if="($page.props.auth?.original_super_admin_id || $page.props.auth?.original_admin_id) && isMinimized"
        class="fixed bottom-4 right-4 z-50"
        role="alert"
        aria-live="polite"
    >
        <Button
            size="sm"
            variant="outline"
            @click="toggleMinimized"
            class="border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-full p-2"
            title="Super Admin Mode - Click to expand"
        >
            <Crown class="h-4 w-4" />
        </Button>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Crown, ArrowLeft, Loader, ChevronDown, ChevronUp } from 'lucide-vue-next';

const $page = usePage();

// State
const isReturning = ref(false);
const isMinimized = ref(false);

// Actions
const returnToSuperAdmin = () => {
    if (isReturning.value) return;

    isReturning.value = true;

    // Determine which return endpoint to use
    const returnUrl = $page.props.auth?.original_super_admin_id
        ? '/super-admin/return-to-super-admin'
        : '/admin/return-to-admin';

    router.post(returnUrl, {}, {
        onFinish: () => {
            isReturning.value = false;
        },
        onError: () => {
            isReturning.value = false;
        },
    });
};

const toggleMinimized = () => {
    isMinimized.value = !isMinimized.value;
};
</script>

<style scoped>
/* Ensure the banner stays above other elements */
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
