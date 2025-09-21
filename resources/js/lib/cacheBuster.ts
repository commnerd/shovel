/**
 * Cache busting utilities for handling deployment updates
 */

interface DeploymentInfo {
    version: string;
    timestamp: string;
    build_number: number;
}

class CacheBuster {
    private deploymentInfo: DeploymentInfo | null = null;
    private checkInterval: number | null = null;

    constructor() {
        this.initialize();
    }

    /**
     * Initialize cache busting system
     */
    private initialize(): void {
        this.loadDeploymentInfo();

        // Check for updates every 30 seconds in development, 5 minutes in production
        const interval = import.meta.env.DEV ? 30000 : 300000;
        this.startPeriodicCheck(interval);

        // Check for updates when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkForUpdates();
            }
        });
    }

    /**
     * Load deployment information from meta tags
     */
    private loadDeploymentInfo(): void {
        const versionMeta = document.querySelector('meta[name="app-version"]');
        const timestampMeta = document.querySelector('meta[name="deployment-timestamp"]');

        if (versionMeta && timestampMeta) {
            this.deploymentInfo = {
                version: versionMeta.getAttribute('content') || '',
                timestamp: timestampMeta.getAttribute('content') || '',
                build_number: 0 // We don't have this in meta tags
            };
        }
    }

    /**
     * Get current deployment version
     */
    public getCurrentVersion(): string {
        return this.deploymentInfo?.version || 'unknown';
    }

    /**
     * Start periodic checking for updates
     */
    private startPeriodicCheck(interval: number): void {
        this.checkInterval = window.setInterval(() => {
            this.checkForUpdates();
        }, interval);
    }

    /**
     * Check for deployment updates
     */
    public async checkForUpdates(): Promise<boolean> {
        try {
            // Fetch deployment marker with cache busting
            const response = await fetch('/deployment-marker.txt?' + Date.now(), {
                cache: 'no-cache',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return false;
            }

            const newDeploymentInfo: DeploymentInfo = await response.json();

            // Compare versions
            if (this.deploymentInfo && newDeploymentInfo.version !== this.deploymentInfo.version) {
                this.handleUpdateAvailable(newDeploymentInfo);
                return true;
            }

            return false;
        } catch (error) {
            console.warn('Failed to check for updates:', error);
            return false;
        }
    }

    /**
     * Handle when an update is available
     */
    private handleUpdateAvailable(newVersion: DeploymentInfo): void {
        console.log(`New deployment available: ${newVersion.version}`);

        // Stop periodic checking
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }

        // Show update notification
        this.showUpdateNotification(newVersion);
    }

    /**
     * Show update notification to user
     */
    private showUpdateNotification(version: DeploymentInfo): void {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-600 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold">Update Available</h4>
                    <p class="text-sm opacity-90">Version ${version.version}</p>
                </div>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mt-3">
                <button
                    class="bg-white text-blue-600 px-4 py-2 rounded text-sm font-medium hover:bg-gray-100 transition-colors"
                    onclick="window.location.reload()"
                >
                    Reload Now
                </button>
                <button
                    class="ml-2 text-white text-sm opacity-75 hover:opacity-100"
                    onclick="this.parentElement.parentElement.remove()"
                >
                    Later
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 30 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 30000);
    }

    /**
     * Force refresh the page with cache busting
     */
    public forceRefresh(): void {
        // Clear any cached data
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
        }

        // Reload with cache busting
        window.location.reload();
    }

    /**
     * Get cache-busted URL for a resource
     */
    public getCacheBustedUrl(url: string): string {
        const version = this.getCurrentVersion();
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}v=${version}`;
    }
}

// Create global instance
export const cacheBuster = new CacheBuster();

// Export for manual use
export default CacheBuster;
