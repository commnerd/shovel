<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentBuildTest extends TestCase
{
    public function test_register_page_uses_relative_imports()
    {
        $registerPath = resource_path('js/pages/auth/Register.vue');
        $this->assertFileExists($registerPath);

        $content = file_get_contents($registerPath);

        // Ensure it uses relative imports to avoid Docker build issues
        $this->assertStringContainsString(
            '../../actions/App/Http/Controllers/Auth/RegisteredUserController',
            $content,
            'Register.vue should use relative import for RegisteredUserController to avoid Docker build issues'
        );

        // Ensure it doesn't use problematic absolute imports
        $this->assertStringNotContainsString(
            '@/actions/App/Http/Controllers/Auth/RegisteredUserController',
            $content,
            'Register.vue should not use absolute imports that fail in Docker builds'
        );
    }

    public function test_required_action_files_exist()
    {
        $registerControllerPath = resource_path('js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts');
        $this->assertFileExists($registerControllerPath);

        // Verify the file has the expected exports
        $content = file_get_contents($registerControllerPath);
        $this->assertStringContainsString('export default RegisteredUserController', $content);
    }

    public function test_impersonation_banner_component_exists()
    {
        $bannerPath = resource_path('js/components/SuperAdminImpersonationBanner.vue');
        $this->assertFileExists($bannerPath);

        $content = file_get_contents($bannerPath);
        $this->assertStringContainsString('original_super_admin_id', $content);
    }
}
