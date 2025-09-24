import type { App } from 'vue';
import { cacheBuster } from '../lib/cacheBuster';

export default {
    install(app: App) {
        // Make cache buster available globally
        app.config.globalProperties.$cacheBuster = cacheBuster;
        app.provide('cacheBuster', cacheBuster);

        // Add cache busting to all asset URLs
        app.config.globalProperties.$asset = (url: string) => {
            return cacheBuster.getCacheBustedUrl(url);
        };

        // Add aggressive cache clearing method
        app.config.globalProperties.$clearAllCaches = () => {
            return cacheBuster.clearAllBrowserCaches();
        };

        // Add force reload with cache busting
        app.config.globalProperties.$forceReload = () => {
            return cacheBuster.forceReloadWithCacheBusting();
        };

        // Intercept fetch requests to add cache busting
        const originalFetch = window.fetch;
        window.fetch = function(input: RequestInfo | URL, init?: RequestInit) {
        let url: string;
        
        if (typeof input === 'string') {
            url = cacheBuster.getCacheBustedUrl(input);
        } else if (input instanceof URL) {
            url = cacheBuster.getCacheBustedUrl(input.toString());
        } else if (input instanceof Request) {
            url = cacheBuster.getCacheBustedUrl(input.url);
        } else {
            url = String(input);
        }

            // Add cache busting headers
            const headers = new Headers(init?.headers);
            headers.set('Cache-Control', 'no-cache');
            headers.set('Pragma', 'no-cache');

            return originalFetch(url, {
                ...init,
                headers,
                cache: 'no-cache'
            });
        };

        // Monitor for deployment updates
        cacheBuster.checkForUpdates();

        console.log('🚀 Cache busting plugin installed');
    }
};