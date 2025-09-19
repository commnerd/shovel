import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
export const index = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/tasks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
index.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
index.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
index.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
const indexForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
indexForm.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::index
* @see app/Http/Controllers/TasksController.php:15
* @route '/dashboard/projects/{project}/tasks'
*/
indexForm.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
export const create = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/tasks/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
create.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
create.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
create.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
const createForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
createForm.get = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::create
* @see app/Http/Controllers/TasksController.php:80
* @route '/dashboard/projects/{project}/tasks/create'
*/
createForm.head = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
export const createSubtask = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createSubtask.url(args, options),
    method: 'get',
})

createSubtask.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/tasks/{task}/subtasks/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
createSubtask.url = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            project: args[0],
            task: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return createSubtask.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
createSubtask.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createSubtask.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
createSubtask.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: createSubtask.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
const createSubtaskForm = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: createSubtask.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
createSubtaskForm.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: createSubtask.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::createSubtask
* @see app/Http/Controllers/TasksController.php:313
* @route '/dashboard/projects/{project}/tasks/{task}/subtasks/create'
*/
createSubtaskForm.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: createSubtask.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

createSubtask.form = createSubtaskForm

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
export const showBreakdown = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBreakdown.url(args, options),
    method: 'get',
})

showBreakdown.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/tasks/{task}/breakdown',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
showBreakdown.url = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            project: args[0],
            task: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return showBreakdown.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
showBreakdown.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBreakdown.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
showBreakdown.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showBreakdown.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
const showBreakdownForm = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showBreakdown.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
showBreakdownForm.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showBreakdown.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::showBreakdown
* @see app/Http/Controllers/TasksController.php:342
* @route '/dashboard/projects/{project}/tasks/{task}/breakdown'
*/
showBreakdownForm.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showBreakdown.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

showBreakdown.form = showBreakdownForm

/**
* @see \App\Http\Controllers\TasksController::store
* @see app/Http/Controllers/TasksController.php:108
* @route '/dashboard/projects/{project}/tasks'
*/
export const store = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/dashboard/projects/{project}/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TasksController::store
* @see app/Http/Controllers/TasksController.php:108
* @route '/dashboard/projects/{project}/tasks'
*/
store.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::store
* @see app/Http/Controllers/TasksController.php:108
* @route '/dashboard/projects/{project}/tasks'
*/
store.post = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::store
* @see app/Http/Controllers/TasksController.php:108
* @route '/dashboard/projects/{project}/tasks'
*/
const storeForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::store
* @see app/Http/Controllers/TasksController.php:108
* @route '/dashboard/projects/{project}/tasks'
*/
storeForm.post = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\TasksController::generateTaskBreakdown
* @see app/Http/Controllers/TasksController.php:380
* @route '/dashboard/projects/{project}/tasks/breakdown'
*/
export const generateTaskBreakdown = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateTaskBreakdown.url(args, options),
    method: 'post',
})

generateTaskBreakdown.definition = {
    methods: ["post"],
    url: '/dashboard/projects/{project}/tasks/breakdown',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TasksController::generateTaskBreakdown
* @see app/Http/Controllers/TasksController.php:380
* @route '/dashboard/projects/{project}/tasks/breakdown'
*/
generateTaskBreakdown.url = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return generateTaskBreakdown.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::generateTaskBreakdown
* @see app/Http/Controllers/TasksController.php:380
* @route '/dashboard/projects/{project}/tasks/breakdown'
*/
generateTaskBreakdown.post = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateTaskBreakdown.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::generateTaskBreakdown
* @see app/Http/Controllers/TasksController.php:380
* @route '/dashboard/projects/{project}/tasks/breakdown'
*/
const generateTaskBreakdownForm = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateTaskBreakdown.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::generateTaskBreakdown
* @see app/Http/Controllers/TasksController.php:380
* @route '/dashboard/projects/{project}/tasks/breakdown'
*/
generateTaskBreakdownForm.post = (args: { project: string | number | { id: string | number } } | [project: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateTaskBreakdown.url(args, options),
    method: 'post',
})

generateTaskBreakdown.form = generateTaskBreakdownForm

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
export const edit = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/dashboard/projects/{project}/tasks/{task}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
edit.url = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            project: args[0],
            task: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return edit.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
edit.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
edit.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
const editForm = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
editForm.get = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TasksController::edit
* @see app/Http/Controllers/TasksController.php:187
* @route '/dashboard/projects/{project}/tasks/{task}/edit'
*/
editForm.head = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\TasksController::update
* @see app/Http/Controllers/TasksController.php:233
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
export const update = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/dashboard/projects/{project}/tasks/{task}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\TasksController::update
* @see app/Http/Controllers/TasksController.php:233
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
update.url = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            project: args[0],
            task: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return update.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::update
* @see app/Http/Controllers/TasksController.php:233
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
update.put = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\TasksController::update
* @see app/Http/Controllers/TasksController.php:233
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
const updateForm = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::update
* @see app/Http/Controllers/TasksController.php:233
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
updateForm.put = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\TasksController::destroy
* @see app/Http/Controllers/TasksController.php:291
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
export const destroy = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/dashboard/projects/{project}/tasks/{task}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TasksController::destroy
* @see app/Http/Controllers/TasksController.php:291
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
destroy.url = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            project: args[0],
            task: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        project: typeof args.project === 'object'
        ? args.project.id
        : args.project,
        task: typeof args.task === 'object'
        ? args.task.id
        : args.task,
    }

    return destroy.definition.url
            .replace('{project}', parsedArgs.project.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TasksController::destroy
* @see app/Http/Controllers/TasksController.php:291
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
destroy.delete = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\TasksController::destroy
* @see app/Http/Controllers/TasksController.php:291
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
const destroyForm = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TasksController::destroy
* @see app/Http/Controllers/TasksController.php:291
* @route '/dashboard/projects/{project}/tasks/{task}'
*/
destroyForm.delete = (args: { project: string | number | { id: string | number }, task: string | number | { id: string | number } } | [project: string | number | { id: string | number }, task: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const TasksController = { index, create, createSubtask, showBreakdown, store, generateTaskBreakdown, edit, update, destroy }

export default TasksController