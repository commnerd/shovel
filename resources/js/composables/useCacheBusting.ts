import { ref, computed } from 'vue';
import { cacheBusting, type CacheBustingOptions } from '@/utils/cacheBusting';

/**
 * Composable for cache busting in Vue components
 */
export function useCacheBusting(options: CacheBustingOptions = {}) {
    const isEnabled = ref(true);
    const customOptions = ref<Record<string, string>>({});

    /**
     * Generate cache busting parameters
     */
    const generateParams = (overrideOptions?: CacheBustingOptions) => {
        if (!isEnabled.value) return new URLSearchParams();

        const mergedOptions = {
            ...options,
            ...overrideOptions,
            custom: { ...customOptions.value, ...overrideOptions?.custom }
        };

        return cacheBusting.generateUrlParams(mergedOptions);
    };

    /**
     * Add cache busting to a URL
     */
    const bustUrl = (url: string, overrideOptions?: CacheBustingOptions) => {
        if (!isEnabled.value) return url;

        const mergedOptions = {
            ...options,
            ...overrideOptions,
            custom: { ...customOptions.value, ...overrideOptions?.custom }
        };

        return cacheBusting.bustUrl(url, mergedOptions);
    };

    /**
     * Add cache busting headers
     */
    const addHeaders = (headers: HeadersInit = {}, overrideOptions?: CacheBustingOptions) => {
        if (!isEnabled.value) return headers;

        const mergedOptions = {
            ...options,
            ...overrideOptions,
            custom: { ...customOptions.value, ...overrideOptions?.custom }
        };

        return cacheBusting.addHeaders(headers, mergedOptions);
    };

    /**
     * Add cache busting to form data
     */
    const addToFormData = (formData: FormData, overrideOptions?: CacheBustingOptions) => {
        if (!isEnabled.value) return formData;

        const mergedOptions = {
            ...options,
            ...overrideOptions,
            custom: { ...customOptions.value, ...overrideOptions?.custom }
        };

        return cacheBusting.addToFormData(formData, mergedOptions);
    };

    /**
     * Add cache busting to an object
     */
    const addToObject = (data: Record<string, any>, overrideOptions?: CacheBustingOptions) => {
        if (!isEnabled.value) return data;

        const mergedOptions = {
            ...options,
            ...overrideOptions,
            custom: { ...customOptions.value, ...overrideOptions?.custom }
        };

        return cacheBusting.addToObject(data, mergedOptions);
    };

    /**
     * Refresh cache busting data
     */
    const refresh = () => {
        cacheBusting.refresh();
    };

    /**
     * Enable/disable cache busting
     */
    const setEnabled = (enabled: boolean) => {
        isEnabled.value = enabled;
    };

    /**
     * Add custom cache busting options
     */
    const addCustomOption = (key: string, value: string) => {
        customOptions.value[key] = value;
    };

    /**
     * Remove custom cache busting option
     */
    const removeCustomOption = (key: string) => {
        delete customOptions.value[key];
    };

    /**
     * Clear all custom options
     */
    const clearCustomOptions = () => {
        customOptions.value = {};
    };

    // Computed properties
    const version = computed(() => cacheBusting.getVersion());
    const timestamp = computed(() => cacheBusting.getTimestamp());
    const isActive = computed(() => isEnabled.value);

    return {
        // State
        isEnabled,
        customOptions,

        // Computed
        version,
        timestamp,
        isActive,

        // Methods
        generateParams,
        bustUrl,
        addHeaders,
        addToFormData,
        addToObject,
        refresh,
        setEnabled,
        addCustomOption,
        removeCustomOption,
        clearCustomOptions
    };
}

/**
 * Composable specifically for form cache busting
 */
export function useFormCacheBusting(options: CacheBustingOptions = {}) {
    const {
        generateParams,
        addToObject,
        addToFormData,
        isEnabled,
        setEnabled
    } = useCacheBusting(options);

    /**
     * Enhance Inertia form data with cache busting
     */
    const enhanceFormData = (formData: Record<string, any>) => {
        if (!isEnabled.value) return formData;
        return addToObject(formData);
    };

    /**
     * Enhance FormData with cache busting
     */
    const enhanceFormDataObject = (formData: FormData) => {
        if (!isEnabled.value) return formData;
        return addToFormData(formData);
    };

    /**
     * Get cache busting parameters for form submission
     */
    const getFormParams = () => {
        if (!isEnabled.value) return {};

        const params = generateParams();
        const result: Record<string, string> = {};
        params.forEach((value, key) => {
            result[key] = value;
        });
        return result;
    };

    return {
        enhanceFormData,
        enhanceFormDataObject,
        getFormParams,
        isEnabled,
        setEnabled
    };
}
