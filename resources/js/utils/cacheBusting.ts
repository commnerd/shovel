/**
 * Cache busting utilities for form submissions and API requests
 */

export interface CacheBustingOptions {
    timestamp?: boolean;
    version?: boolean;
    random?: boolean;
    custom?: Record<string, string>;
}

export class CacheBusting {
    private static instance: CacheBusting;
    private version: string = '';
    private timestamp: number = 0;

    private constructor() {
        this.initialize();
    }

    public static getInstance(): CacheBusting {
        if (!CacheBusting.instance) {
            CacheBusting.instance = new CacheBusting();
        }
        return CacheBusting.instance;
    }

    private initialize(): void {
        // Get version from meta tag
        const versionMeta = document.querySelector('meta[name="app-version"]');
        this.version = versionMeta?.getAttribute('content') || '1.0.0';

        // Get timestamp from meta tag
        const timestampMeta = document.querySelector('meta[name="deployment-timestamp"]');
        const timestampStr = timestampMeta?.getAttribute('content') || '';
        this.timestamp = timestampStr ? new Date(timestampStr).getTime() : Date.now();
    }

    /**
     * Generate cache busting parameters for URLs
     */
    public generateUrlParams(options: CacheBustingOptions = {}): URLSearchParams {
        const params = new URLSearchParams();

        if (options.timestamp !== false) {
            params.set('_t', this.timestamp.toString());
        }

        if (options.version !== false) {
            params.set('_v', this.version);
        }

        if (options.random !== false) {
            params.set('_r', Math.random().toString(36).substring(2, 15));
        }

        if (options.custom) {
            Object.entries(options.custom).forEach(([key, value]) => {
                params.set(key, value);
            });
        }

        return params;
    }

    /**
     * Add cache busting parameters to a URL
     */
    public bustUrl(url: string, options: CacheBustingOptions = {}): string {
        const urlObj = new URL(url, window.location.origin);
        const params = this.generateUrlParams(options);

        // Add cache busting parameters to existing search params
        params.forEach((value, key) => {
            urlObj.searchParams.set(key, value);
        });

        return urlObj.toString();
    }

    /**
     * Add cache busting headers to a request
     */
    public addHeaders(headers: HeadersInit = {}, options: CacheBustingOptions = {}): HeadersInit {
        const newHeaders = { ...headers };

        if (options.timestamp !== false) {
            (newHeaders as any)['X-Cache-Bust-Timestamp'] = this.timestamp.toString();
        }

        if (options.version !== false) {
            (newHeaders as any)['X-Cache-Bust-Version'] = this.version;
        }

        if (options.random !== false) {
            (newHeaders as any)['X-Cache-Bust-Random'] = Math.random().toString(36).substring(2, 15);
        }

        if (options.custom) {
            Object.entries(options.custom).forEach(([key, value]) => {
                (newHeaders as any)[key] = value;
            });
        }

        return newHeaders;
    }

    /**
     * Add cache busting data to form data
     */
    public addToFormData(formData: FormData, options: CacheBustingOptions = {}): FormData {
        const newFormData = new FormData();

        // Copy existing form data
        for (const [key, value] of formData.entries()) {
            newFormData.set(key, value);
        }

        if (options.timestamp !== false) {
            newFormData.set('_cache_bust_timestamp', this.timestamp.toString());
        }

        if (options.version !== false) {
            newFormData.set('_cache_bust_version', this.version);
        }

        if (options.random !== false) {
            newFormData.set('_cache_bust_random', Math.random().toString(36).substring(2, 15));
        }

        if (options.custom) {
            Object.entries(options.custom).forEach(([key, value]) => {
                newFormData.set(key, value);
            });
        }

        return newFormData;
    }

    /**
     * Add cache busting data to an object
     */
    public addToObject(data: Record<string, any>, options: CacheBustingOptions = {}): Record<string, any> {
        const newData = { ...data };

        if (options.timestamp !== false) {
            newData._cache_bust_timestamp = this.timestamp;
        }

        if (options.version !== false) {
            newData._cache_bust_version = this.version;
        }

        if (options.random !== false) {
            newData._cache_bust_random = Math.random().toString(36).substring(2, 15);
        }

        if (options.custom) {
            Object.entries(options.custom).forEach(([key, value]) => {
                newData[key] = value;
            });
        }

        return newData;
    }

    /**
     * Refresh the cache busting data
     */
    public refresh(): void {
        this.initialize();
    }

    /**
     * Get current version
     */
    public getVersion(): string {
        return this.version;
    }

    /**
     * Get current timestamp
     */
    public getTimestamp(): number {
        return this.timestamp;
    }
}

// Export singleton instance
export const cacheBusting = CacheBusting.getInstance();

// Export convenience functions
export const bustUrl = (url: string, options?: CacheBustingOptions) =>
    cacheBusting.bustUrl(url, options);

export const addCacheBustingHeaders = (headers?: HeadersInit, options?: CacheBustingOptions) =>
    cacheBusting.addHeaders(headers, options);

export const addCacheBustingToFormData = (formData: FormData, options?: CacheBustingOptions) =>
    cacheBusting.addToFormData(formData, options);

export const addCacheBustingToObject = (data: Record<string, any>, options?: CacheBustingOptions) =>
    cacheBusting.addToObject(data, options);
