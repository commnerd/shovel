import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/organization/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::create
* @see app/Http/Controllers/OrganizationController.php:19
* @route '/organization/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\OrganizationController::store
* @see app/Http/Controllers/OrganizationController.php:36
* @route '/organization/create'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/organization/create',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrganizationController::store
* @see app/Http/Controllers/OrganizationController.php:36
* @route '/organization/create'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganizationController::store
* @see app/Http/Controllers/OrganizationController.php:36
* @route '/organization/create'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganizationController::store
* @see app/Http/Controllers/OrganizationController.php:36
* @route '/organization/create'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganizationController::store
* @see app/Http/Controllers/OrganizationController.php:36
* @route '/organization/create'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
export const confirmRegistration = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirmRegistration.url(options),
    method: 'get',
})

confirmRegistration.definition = {
    methods: ["get","head"],
    url: '/registration/confirm-organization',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
confirmRegistration.url = (options?: RouteQueryOptions) => {
    return confirmRegistration.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
confirmRegistration.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirmRegistration.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
confirmRegistration.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: confirmRegistration.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
const confirmRegistrationForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: confirmRegistration.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
confirmRegistrationForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: confirmRegistration.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmRegistration
* @see app/Http/Controllers/OrganizationController.php:101
* @route '/registration/confirm-organization'
*/
confirmRegistrationForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: confirmRegistration.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

confirmRegistration.form = confirmRegistrationForm

/**
* @see \App\Http\Controllers\OrganizationController::confirmStore
* @see app/Http/Controllers/OrganizationController.php:119
* @route '/registration/confirm-organization'
*/
export const confirmStore = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmStore.url(options),
    method: 'post',
})

confirmStore.definition = {
    methods: ["post"],
    url: '/registration/confirm-organization',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrganizationController::confirmStore
* @see app/Http/Controllers/OrganizationController.php:119
* @route '/registration/confirm-organization'
*/
confirmStore.url = (options?: RouteQueryOptions) => {
    return confirmStore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrganizationController::confirmStore
* @see app/Http/Controllers/OrganizationController.php:119
* @route '/registration/confirm-organization'
*/
confirmStore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmStore.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmStore
* @see app/Http/Controllers/OrganizationController.php:119
* @route '/registration/confirm-organization'
*/
const confirmStoreForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: confirmStore.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrganizationController::confirmStore
* @see app/Http/Controllers/OrganizationController.php:119
* @route '/registration/confirm-organization'
*/
confirmStoreForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: confirmStore.url(options),
    method: 'post',
})

confirmStore.form = confirmStoreForm

const OrganizationController = { create, store, confirmRegistration, confirmStore }

export default OrganizationController