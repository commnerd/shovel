import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\UserManagementController::returnToAdmin
* @see app/Http/Controllers/Admin/UserManagementController.php:198
* @route '/admin/return-to-admin'
*/
export const returnToAdmin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToAdmin.url(options),
    method: 'post',
})

returnToAdmin.definition = {
    methods: ["post"],
    url: '/admin/return-to-admin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::returnToAdmin
* @see app/Http/Controllers/Admin/UserManagementController.php:198
* @route '/admin/return-to-admin'
*/
returnToAdmin.url = (options?: RouteQueryOptions) => {
    return returnToAdmin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::returnToAdmin
* @see app/Http/Controllers/Admin/UserManagementController.php:198
* @route '/admin/return-to-admin'
*/
returnToAdmin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToAdmin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::returnToAdmin
* @see app/Http/Controllers/Admin/UserManagementController.php:198
* @route '/admin/return-to-admin'
*/
const returnToAdminForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: returnToAdmin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::returnToAdmin
* @see app/Http/Controllers/Admin/UserManagementController.php:198
* @route '/admin/return-to-admin'
*/
returnToAdminForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: returnToAdmin.url(options),
    method: 'post',
})

returnToAdmin.form = returnToAdminForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::index
* @see app/Http/Controllers/Admin/UserManagementController.php:74
* @route '/admin/users'
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
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
export const searchUsers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchUsers.url(options),
    method: 'get',
})

searchUsers.definition = {
    methods: ["get","head"],
    url: '/admin/users/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
searchUsers.url = (options?: RouteQueryOptions) => {
    return searchUsers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
searchUsers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
searchUsers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: searchUsers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
const searchUsersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
*/
searchUsersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: searchUsers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::searchUsers
* @see app/Http/Controllers/Admin/UserManagementController.php:16
* @route '/admin/users/search'
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
* @see \App\Http\Controllers\Admin\UserManagementController::loginAsUser
* @see app/Http/Controllers/Admin/UserManagementController.php:156
* @route '/admin/users/{user}/login-as'
*/
export const loginAsUser = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginAsUser.url(args, options),
    method: 'post',
})

loginAsUser.definition = {
    methods: ["post"],
    url: '/admin/users/{user}/login-as',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::loginAsUser
* @see app/Http/Controllers/Admin/UserManagementController.php:156
* @route '/admin/users/{user}/login-as'
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
* @see \App\Http\Controllers\Admin\UserManagementController::loginAsUser
* @see app/Http/Controllers/Admin/UserManagementController.php:156
* @route '/admin/users/{user}/login-as'
*/
loginAsUser.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginAsUser.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::loginAsUser
* @see app/Http/Controllers/Admin/UserManagementController.php:156
* @route '/admin/users/{user}/login-as'
*/
const loginAsUserForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loginAsUser.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::loginAsUser
* @see app/Http/Controllers/Admin/UserManagementController.php:156
* @route '/admin/users/{user}/login-as'
*/
loginAsUserForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loginAsUser.url(args, options),
    method: 'post',
})

loginAsUser.form = loginAsUserForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::approve
* @see app/Http/Controllers/Admin/UserManagementController.php:233
* @route '/admin/users/{user}/approve'
*/
export const approve = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/admin/users/{user}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::approve
* @see app/Http/Controllers/Admin/UserManagementController.php:233
* @route '/admin/users/{user}/approve'
*/
approve.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return approve.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::approve
* @see app/Http/Controllers/Admin/UserManagementController.php:233
* @route '/admin/users/{user}/approve'
*/
approve.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::approve
* @see app/Http/Controllers/Admin/UserManagementController.php:233
* @route '/admin/users/{user}/approve'
*/
const approveForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::approve
* @see app/Http/Controllers/Admin/UserManagementController.php:233
* @route '/admin/users/{user}/approve'
*/
approveForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, options),
    method: 'post',
})

approve.form = approveForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::assignRole
* @see app/Http/Controllers/Admin/UserManagementController.php:284
* @route '/admin/users/{user}/assign-role'
*/
export const assignRole = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assignRole.url(args, options),
    method: 'post',
})

assignRole.definition = {
    methods: ["post"],
    url: '/admin/users/{user}/assign-role',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::assignRole
* @see app/Http/Controllers/Admin/UserManagementController.php:284
* @route '/admin/users/{user}/assign-role'
*/
assignRole.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return assignRole.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::assignRole
* @see app/Http/Controllers/Admin/UserManagementController.php:284
* @route '/admin/users/{user}/assign-role'
*/
assignRole.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assignRole.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::assignRole
* @see app/Http/Controllers/Admin/UserManagementController.php:284
* @route '/admin/users/{user}/assign-role'
*/
const assignRoleForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assignRole.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::assignRole
* @see app/Http/Controllers/Admin/UserManagementController.php:284
* @route '/admin/users/{user}/assign-role'
*/
assignRoleForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assignRole.url(args, options),
    method: 'post',
})

assignRole.form = assignRoleForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeRole
* @see app/Http/Controllers/Admin/UserManagementController.php:322
* @route '/admin/users/{user}/remove-role'
*/
export const removeRole = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeRole.url(args, options),
    method: 'delete',
})

removeRole.definition = {
    methods: ["delete"],
    url: '/admin/users/{user}/remove-role',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeRole
* @see app/Http/Controllers/Admin/UserManagementController.php:322
* @route '/admin/users/{user}/remove-role'
*/
removeRole.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return removeRole.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeRole
* @see app/Http/Controllers/Admin/UserManagementController.php:322
* @route '/admin/users/{user}/remove-role'
*/
removeRole.delete = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeRole.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeRole
* @see app/Http/Controllers/Admin/UserManagementController.php:322
* @route '/admin/users/{user}/remove-role'
*/
const removeRoleForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeRole.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeRole
* @see app/Http/Controllers/Admin/UserManagementController.php:322
* @route '/admin/users/{user}/remove-role'
*/
removeRoleForm.delete = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeRole.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

removeRole.form = removeRoleForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::addToGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:403
* @route '/admin/users/{user}/add-to-group'
*/
export const addToGroup = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToGroup.url(args, options),
    method: 'post',
})

addToGroup.definition = {
    methods: ["post"],
    url: '/admin/users/{user}/add-to-group',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::addToGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:403
* @route '/admin/users/{user}/add-to-group'
*/
addToGroup.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return addToGroup.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::addToGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:403
* @route '/admin/users/{user}/add-to-group'
*/
addToGroup.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToGroup.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::addToGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:403
* @route '/admin/users/{user}/add-to-group'
*/
const addToGroupForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addToGroup.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::addToGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:403
* @route '/admin/users/{user}/add-to-group'
*/
addToGroupForm.post = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addToGroup.url(args, options),
    method: 'post',
})

addToGroup.form = addToGroupForm

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeFromGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:360
* @route '/admin/users/{user}/remove-from-group'
*/
export const removeFromGroup = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeFromGroup.url(args, options),
    method: 'delete',
})

removeFromGroup.definition = {
    methods: ["delete"],
    url: '/admin/users/{user}/remove-from-group',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeFromGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:360
* @route '/admin/users/{user}/remove-from-group'
*/
removeFromGroup.url = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return removeFromGroup.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeFromGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:360
* @route '/admin/users/{user}/remove-from-group'
*/
removeFromGroup.delete = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeFromGroup.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeFromGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:360
* @route '/admin/users/{user}/remove-from-group'
*/
const removeFromGroupForm = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeFromGroup.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\UserManagementController::removeFromGroup
* @see app/Http/Controllers/Admin/UserManagementController.php:360
* @route '/admin/users/{user}/remove-from-group'
*/
removeFromGroupForm.delete = (args: { user: string | number | { id: string | number } } | [user: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: removeFromGroup.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

removeFromGroup.form = removeFromGroupForm

const UserManagementController = { returnToAdmin, index, searchUsers, loginAsUser, approve, assignRole, removeRole, addToGroup, removeFromGroup }

export default UserManagementController