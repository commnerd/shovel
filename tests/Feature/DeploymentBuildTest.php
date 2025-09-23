<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentBuildTest extends TestCase
{
    public function test_register_page_uses_direct_form_action()
    {
        $registerPath = resource_path('js/pages/auth/Register.vue');
        $this->assertFileExists($registerPath);

        $content = file_get_contents($registerPath);

        // Ensure it uses direct form action instead of Wayfinder-generated actions
        $this->assertStringContainsString(
            'action="/register"',
            $content,
            'Register.vue should use direct form action instead of Wayfinder-generated actions'
        );

        // Ensure it doesn't use Wayfinder-generated imports
        $this->assertStringNotContainsString(
            '@/actions/App/Http/Controllers/Auth/RegisteredUserController',
            $content,
            'Register.vue should not use Wayfinder-generated action imports'
        );
    }

    public function test_wayfinder_files_removed()
    {
        $registerControllerPath = resource_path('js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts');

        // Verify Wayfinder-generated files no longer exist
        $this->assertFileDoesNotExist($registerControllerPath, 'Wayfinder-generated action files should be removed');

        // Verify actions directory doesn't exist
        $actionsDir = resource_path('js/actions');
        $this->assertDirectoryDoesNotExist($actionsDir, 'Wayfinder actions directory should be removed');
    }

    public function test_impersonation_banner_component_exists()
    {
        $bannerPath = resource_path('js/components/SuperAdminImpersonationBanner.vue');
        $this->assertFileExists($bannerPath);

        $content = file_get_contents($bannerPath);
        $this->assertStringContainsString('original_super_admin_id', $content);
    }
}
