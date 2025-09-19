import SettingsController from './SettingsController'
import ProfileController from './ProfileController'
import PasswordController from './PasswordController'

const Settings = {
    SettingsController: Object.assign(SettingsController, SettingsController),
    ProfileController: Object.assign(ProfileController, ProfileController),
    PasswordController: Object.assign(PasswordController, PasswordController),
}

export default Settings