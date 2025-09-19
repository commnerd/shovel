import WaitlistController from './WaitlistController'
import DashboardController from './DashboardController'
import Settings from './Settings'
import SuperAdmin from './SuperAdmin'
import Admin from './Admin'
import ProjectsController from './ProjectsController'
import TasksController from './TasksController'
import Auth from './Auth'
import OrganizationController from './OrganizationController'

const Controllers = {
    WaitlistController: Object.assign(WaitlistController, WaitlistController),
    DashboardController: Object.assign(DashboardController, DashboardController),
    Settings: Object.assign(Settings, Settings),
    SuperAdmin: Object.assign(SuperAdmin, SuperAdmin),
    Admin: Object.assign(Admin, Admin),
    ProjectsController: Object.assign(ProjectsController, ProjectsController),
    TasksController: Object.assign(TasksController, TasksController),
    Auth: Object.assign(Auth, Auth),
    OrganizationController: Object.assign(OrganizationController, OrganizationController),
}

export default Controllers