import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\WaitlistController::store
* @see app/Http/Controllers/WaitlistController.php:10
* @route '/waitlist'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/waitlist',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WaitlistController::store
* @see app/Http/Controllers/WaitlistController.php:10
* @route '/waitlist'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WaitlistController::store
* @see app/Http/Controllers/WaitlistController.php:10
* @route '/waitlist'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\WaitlistController::store
* @see app/Http/Controllers/WaitlistController.php:10
* @route '/waitlist'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\WaitlistController::store
* @see app/Http/Controllers/WaitlistController.php:10
* @route '/waitlist'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const WaitlistController = { store }

export default WaitlistController