import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/system',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::index
* @see app/Http/Controllers/Settings/SettingsController.php:15
* @route '/settings/system'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateAI
* @see app/Http/Controllers/Settings/SettingsController.php:98
* @route '/settings/ai'
*/
export const updateAI = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateAI.url(options),
    method: 'post',
})

updateAI.definition = {
    methods: ["post"],
    url: '/settings/ai',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateAI
* @see app/Http/Controllers/Settings/SettingsController.php:98
* @route '/settings/ai'
*/
updateAI.url = (options?: RouteQueryOptions) => {
    return updateAI.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateAI
* @see app/Http/Controllers/Settings/SettingsController.php:98
* @route '/settings/ai'
*/
updateAI.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateAI
* @see app/Http/Controllers/Settings/SettingsController.php:98
* @route '/settings/ai'
*/
const updateAIForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateAI
* @see app/Http/Controllers/Settings/SettingsController.php:98
* @route '/settings/ai'
*/
updateAIForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateAI.url(options),
    method: 'post',
})

updateAI.form = updateAIForm

/**
* @see \App\Http\Controllers\Settings\SettingsController::testAI
* @see app/Http/Controllers/Settings/SettingsController.php:162
* @route '/settings/ai/test'
*/
export const testAI = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testAI.url(options),
    method: 'post',
})

testAI.definition = {
    methods: ["post"],
    url: '/settings/ai/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\SettingsController::testAI
* @see app/Http/Controllers/Settings/SettingsController.php:162
* @route '/settings/ai/test'
*/
testAI.url = (options?: RouteQueryOptions) => {
    return testAI.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SettingsController::testAI
* @see app/Http/Controllers/Settings/SettingsController.php:162
* @route '/settings/ai/test'
*/
testAI.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::testAI
* @see app/Http/Controllers/Settings/SettingsController.php:162
* @route '/settings/ai/test'
*/
const testAIForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: testAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::testAI
* @see app/Http/Controllers/Settings/SettingsController.php:162
* @route '/settings/ai/test'
*/
testAIForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: testAI.url(options),
    method: 'post',
})

testAI.form = testAIForm

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateDefaultAI
* @see app/Http/Controllers/Settings/SettingsController.php:135
* @route '/settings/ai/default'
*/
export const updateDefaultAI = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateDefaultAI.url(options),
    method: 'post',
})

updateDefaultAI.definition = {
    methods: ["post"],
    url: '/settings/ai/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateDefaultAI
* @see app/Http/Controllers/Settings/SettingsController.php:135
* @route '/settings/ai/default'
*/
updateDefaultAI.url = (options?: RouteQueryOptions) => {
    return updateDefaultAI.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateDefaultAI
* @see app/Http/Controllers/Settings/SettingsController.php:135
* @route '/settings/ai/default'
*/
updateDefaultAI.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateDefaultAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateDefaultAI
* @see app/Http/Controllers/Settings/SettingsController.php:135
* @route '/settings/ai/default'
*/
const updateDefaultAIForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateDefaultAI.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\SettingsController::updateDefaultAI
* @see app/Http/Controllers/Settings/SettingsController.php:135
* @route '/settings/ai/default'
*/
updateDefaultAIForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateDefaultAI.url(options),
    method: 'post',
})

updateDefaultAI.form = updateDefaultAIForm

const SettingsController = { index, updateAI, testAI, updateDefaultAI }

export default SettingsController