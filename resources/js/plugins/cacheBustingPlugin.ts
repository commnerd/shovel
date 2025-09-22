import { App } from 'vue';
import { router } from '@inertiajs/vue3';
import { cacheBusting } from '@/utils/cacheBusting';

/**
 * Inertia plugin for automatic cache busting
 */
export function cacheBustingPlugin(app: App) {
    // Intercept Inertia requests to add cache busting
    router.on('before', (event) => {
        // Only add cache busting to POST, PUT, PATCH, DELETE requests
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(event.detail.visit.method)) {
            const originalData = event.detail.visit.data;

            if (originalData instanceof FormData) {
                // For FormData, add cache busting parameters
                const enhancedData = cacheBusting.addToFormData(originalData);
                event.detail.visit.data = enhancedData;
            } else if (typeof originalData === 'object' && originalData !== null) {
                // For object data, add cache busting properties
                const enhancedData = cacheBusting.addToObject(originalData);
                event.detail.visit.data = enhancedData;
            }
        }
    });

    // Add cache busting headers to all requests
    router.on('before', (event) => {
        const originalHeaders = event.detail.visit.headers || {};
        const enhancedHeaders = cacheBusting.addHeaders(originalHeaders) as Record<string, string>;
        event.detail.visit.headers = enhancedHeaders;
    });

    // Add cache busting to URLs for GET requests with data
    router.on('before', (event) => {
        if (event.detail.visit.method === 'get' && event.detail.visit.data) {
            const originalUrl = event.detail.visit.url.toString();
            const enhancedUrl = cacheBusting.bustUrl(originalUrl);
            event.detail.visit.url = enhancedUrl as any;
        }
    });
}

/**
 * Vue plugin for cache busting
 */
export default {
    install(app: App) {
        cacheBustingPlugin(app);
    }
};
