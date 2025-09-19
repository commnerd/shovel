import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::index
* @see app/Http/Controllers/ProjectsController.php:15
* @route '/dashboard/projects'
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
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::create
* @see app/Http/Controllers/ProjectsController.php:64
* @route '/dashboard/projects/create'
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
* @see \App\Http\Controllers\ProjectsController::createTasksPage
* @see app/Http/Controllers/ProjectsController.php:184
* @route '/dashboard/projects/create/tasks'
*/
export const createTasksPage = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createTasksPage.url(options),
    method: 'post',
})

createTasksPage.definition = {
    methods: ["post"],
    url: '/dashboard/projects/create/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProjectsController::createTasksPage
* @see app/Http/Controllers/ProjectsController.php:184
* @route '/dashboard/projects/create/tasks'
*/
createTasksPage.url = (options?: RouteQueryOptions) => {
    return createTasksPage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::createTasksPage
* @see app/Http/Controllers/ProjectsController.php:184
* @route '/dashboard/projects/create/tasks'
*/
createTasksPage.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createTasksPage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::createTasksPage
* @see app/Http/Controllers/ProjectsController.php:184
* @route '/dashboard/projects/create/tasks'
*/
const createTasksPageForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: createTasksPage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::createTasksPage
* @see app/Http/Controllers/ProjectsController.php:184
* @route '/dashboard/projects/create/tasks'
*/
createTasksPageForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: createTasksPage.url(options),
    method: 'post',
})

createTasksPage.form = createTasksPageForm

/**
* @see \App\Http\Controllers\ProjectsController::store
* @see app/Http/Controllers/ProjectsController.php:332
* @route '/dashboard/projects'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/dashboard/projects',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProjectsController::store
* @see app/Http/Controllers/ProjectsController.php:332
* @route '/dashboard/projects'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::store
* @see app/Http/Controllers/ProjectsController.php:332
* @route '/dashboard/projects'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::store
* @see app/Http/Controllers/ProjectsController.php:332
* @route '/dashboard/projects'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::store
* @see app/Http/Controllers/ProjectsController.php:332
* @route '/dashboard/projects'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
export const edit = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
edit.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { project: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { project: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            project: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
    }

    return edit.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
edit.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
edit.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
const editForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
editForm.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProjectsController::edit
* @see app/Http/Controllers/ProjectsController.php:88
* @route '/dashboard/projects/{project}/edit'
*/
editForm.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\ProjectsController::update
* @see app/Http/Controllers/ProjectsController.php:109
* @route '/dashboard/projects/{project}'
*/
export const update = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/dashboard/projects/{project}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ProjectsController::update
* @see app/Http/Controllers/ProjectsController.php:109
* @route '/dashboard/projects/{project}'
*/
update.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { project: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { project: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            project: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
    }

    return update.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::update
* @see app/Http/Controllers/ProjectsController.php:109
* @route '/dashboard/projects/{project}'
*/
update.put = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\ProjectsController::update
* @see app/Http/Controllers/ProjectsController.php:109
* @route '/dashboard/projects/{project}'
*/
const updateForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::update
* @see app/Http/Controllers/ProjectsController.php:109
* @route '/dashboard/projects/{project}'
*/
updateForm.put = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\ProjectsController::destroy
* @see app/Http/Controllers/ProjectsController.php:138
* @route '/dashboard/projects/{project}'
*/
export const destroy = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/dashboard/projects/{project}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ProjectsController::destroy
* @see app/Http/Controllers/ProjectsController.php:138
* @route '/dashboard/projects/{project}'
*/
destroy.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { project: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { project: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            project: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
    }

    return destroy.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProjectsController::destroy
* @see app/Http/Controllers/ProjectsController.php:138
* @route '/dashboard/projects/{project}'
*/
destroy.delete = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\ProjectsController::destroy
* @see app/Http/Controllers/ProjectsController.php:138
* @route '/dashboard/projects/{project}'
*/
const destroyForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProjectsController::destroy
* @see app/Http/Controllers/ProjectsController.php:138
* @route '/dashboard/projects/{project}'
*/
destroyForm.delete = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const ProjectsController = { index, create, createTasksPage, store, edit, update, destroy }

export default ProjectsController