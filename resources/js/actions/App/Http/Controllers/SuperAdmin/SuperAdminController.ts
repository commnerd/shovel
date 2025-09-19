import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::returnToSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:216
* @route '/super-admin/return-to-super-admin'
*/
export const returnToSuperAdmin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToSuperAdmin.url(options),
    method: 'post',
})

returnToSuperAdmin.definition = {
    methods: ["post"],
    url: '/super-admin/return-to-super-admin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::returnToSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:216
* @route '/super-admin/return-to-super-admin'
*/
returnToSuperAdmin.url = (options?: RouteQueryOptions) => {
    return returnToSuperAdmin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::returnToSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:216
* @route '/super-admin/return-to-super-admin'
*/
returnToSuperAdmin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToSuperAdmin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::returnToSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:216
* @route '/super-admin/return-to-super-admin'
*/
const returnToSuperAdminForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: returnToSuperAdmin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::returnToSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:216
* @route '/super-admin/return-to-super-admin'
*/
returnToSuperAdminForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: returnToSuperAdmin.url(options),
    method: 'post',
})

returnToSuperAdmin.form = returnToSuperAdminForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/super-admin',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::index
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:17
* @route '/super-admin'
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
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
export const users = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})

users.definition = {
    methods: ["get","head"],
    url: '/super-admin/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
users.url = (options?: RouteQueryOptions) => {
    return users.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
users.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
users.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: users.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
const usersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: users.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
usersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: users.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::users
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:89
* @route '/super-admin/users'
*/
usersForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: users.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

users.form = usersForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
export const organizations = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: organizations.url(options),
    method: 'get',
})

organizations.definition = {
    methods: ["get","head"],
    url: '/super-admin/organizations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
organizations.url = (options?: RouteQueryOptions) => {
    return organizations.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
organizations.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: organizations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
organizations.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: organizations.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
const organizationsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: organizations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
organizationsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: organizations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::organizations
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:139
* @route '/super-admin/organizations'
*/
organizationsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: organizations.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

organizations.form = organizationsForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
export const searchUsers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchUsers.url(options),
    method: 'get',
})

searchUsers.definition = {
    methods: ["get","head"],
    url: '/super-admin/users/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
searchUsers.url = (options?: RouteQueryOptions) => {
    return searchUsers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
searchUsers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
searchUsers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: searchUsers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
const searchUsersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
searchUsersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::searchUsers
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:34
* @route '/super-admin/users/search'
*/
searchUsersForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: searchUsers.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

searchUsers.form = searchUsersForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::loginAsUser
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:182
* @route '/super-admin/users/{user}/login-as'
*/
export const loginAsUser = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginAsUser.url(args, options),
    method: 'post',
})

loginAsUser.definition = {
    methods: ["post"],
    url: '/super-admin/users/{user}/login-as',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::loginAsUser
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:182
* @route '/super-admin/users/{user}/login-as'
*/
loginAsUser.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return loginAsUser.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::loginAsUser
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:182
* @route '/super-admin/users/{user}/login-as'
*/
loginAsUser.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginAsUser.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::loginAsUser
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:182
* @route '/super-admin/users/{user}/login-as'
*/
const loginAsUserForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loginAsUser.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::loginAsUser
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:182
* @route '/super-admin/users/{user}/login-as'
*/
loginAsUserForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loginAsUser.url(args, options),
    method: 'post',
})

loginAsUser.form = loginAsUserForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::assignSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:250
* @route '/super-admin/users/{user}/assign-super-admin'
*/
export const assignSuperAdmin = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assignSuperAdmin.url(args, options),
    method: 'post',
})

assignSuperAdmin.definition = {
    methods: ["post"],
    url: '/super-admin/users/{user}/assign-super-admin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::assignSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:250
* @route '/super-admin/users/{user}/assign-super-admin'
*/
assignSuperAdmin.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return assignSuperAdmin.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::assignSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:250
* @route '/super-admin/users/{user}/assign-super-admin'
*/
assignSuperAdmin.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assignSuperAdmin.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::assignSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:250
* @route '/super-admin/users/{user}/assign-super-admin'
*/
const assignSuperAdminForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assignSuperAdmin.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::assignSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:250
* @route '/super-admin/users/{user}/assign-super-admin'
*/
assignSuperAdminForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assignSuperAdmin.url(args, options),
    method: 'post',
})

assignSuperAdmin.form = assignSuperAdminForm

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::removeSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:271
* @route '/super-admin/users/{user}/remove-super-admin'
*/
export const removeSuperAdmin = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: removeSuperAdmin.url(args, options),
    method: 'post',
})

removeSuperAdmin.definition = {
    methods: ["post"],
    url: '/super-admin/users/{user}/remove-super-admin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::removeSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:271
* @route '/super-admin/users/{user}/remove-super-admin'
*/
removeSuperAdmin.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return removeSuperAdmin.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::removeSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:271
* @route '/super-admin/users/{user}/remove-super-admin'
*/
removeSuperAdmin.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: removeSuperAdmin.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::removeSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:271
* @route '/super-admin/users/{user}/remove-super-admin'
*/
const removeSuperAdminForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeSuperAdmin.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SuperAdmin\SuperAdminController::removeSuperAdmin
* @see app/Http/Controllers/SuperAdmin/SuperAdminController.php:271
* @route '/super-admin/users/{user}/remove-super-admin'
*/
removeSuperAdminForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeSuperAdmin.url(args, options),
    method: 'post',
})

removeSuperAdmin.form = removeSuperAdminForm

const SuperAdminController = { returnToSuperAdmin, index, users, organizations, searchUsers, loginAsUser, assignSuperAdmin, removeSuperAdmin }

export default SuperAdminController