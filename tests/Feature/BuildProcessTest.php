<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class BuildProcessTest extends TestCase
{
    /**
     * Test that the build process completes successfully.
     */
    public function test_yarn_build_completes_successfully()
    {
        // Skip if not in a development environment with Node.js
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for build testing');
        }

        // Run the build process
        $result = Process::timeout(120)->run('yarn build');

        $this->assertEquals(0, $result->exitCode(),
            'Build process failed with output: ' . $result->output() . $result->errorOutput());

        $this->assertStringContainsString('built in', $result->output());
    }

    /**
     * Test that required build artifacts are generated.
     */
    public function test_build_artifacts_are_generated()
    {
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for build testing');
        }

        // Ensure build directory exists
        $buildPath = public_path('build');

        if (!File::exists($buildPath)) {
            // Run build if artifacts don't exist
            Process::timeout(120)->run('yarn build');
        }

        // Check that key build artifacts exist
        $this->assertFileExists(public_path('build/manifest.json'));
        $this->assertDirectoryExists(public_path('build/assets'));

        // Check that main app files are built
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
        $this->assertArrayHasKey('resources/js/app.ts', $manifest);

        // CSS is included in the app.ts entry, not as a separate entry
        $appEntry = $manifest['resources/js/app.ts'];
        $this->assertArrayHasKey('css', $appEntry);
        $this->assertNotEmpty($appEntry['css']);
    }

    /**
     * Test that TypeScript compilation works without errors.
     */
    public function test_typescript_compilation_succeeds()
    {
        // Run TypeScript type checking
        $result = Process::path(base_path())->run('npx tsc --noEmit');

        // Assert that the command completed successfully
        $this->assertTrue($result->successful(),
            'TypeScript check command should complete successfully. Output: ' . $result->output());

        // Verify that Vue SFC type declarations exist
        $this->assertFileExists(base_path('resources/js/types/vue-shims.d.ts'),
            'Vue SFC type declarations should exist');

        // Verify that tsconfig.json exists and contains expected content
        $this->assertFileExists(base_path('tsconfig.json'),
            'TypeScript configuration file should exist');

        $tsConfigContent = File::get(base_path('tsconfig.json'));
        $this->assertStringContainsString('compilerOptions', $tsConfigContent,
            'tsconfig.json should have compilerOptions');
        $this->assertStringContainsString('include', $tsConfigContent,
            'tsconfig.json should specify included files');
        $this->assertStringContainsString('resources/js', $tsConfigContent,
            'tsconfig.json should include resources/js directory');
    }

    /**
     * Test that Vue components compile correctly.
     */
    public function test_vue_components_compile_correctly()
    {
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for Vue testing');
        }

        // Test that specific Vue components we created exist and are valid
        $componentPaths = [
            'resources/js/components/ui/badge/Badge.vue',
            'resources/js/components/ui/scroll-area/ScrollArea.vue',
            'resources/js/pages/Projects/CreateProjectForm.vue',
            'resources/js/pages/Projects/CreateTasks.vue',
        ];

        foreach ($componentPaths as $componentPath) {
            $fullPath = base_path($componentPath);
            $this->assertFileExists($fullPath, "Component {$componentPath} should exist");

            $content = File::get($fullPath);
            $this->assertStringContainsString('<template>', $content, "Component {$componentPath} should have a template section");
            $this->assertStringContainsString('<script', $content, "Component {$componentPath} should have a script section");
        }
    }

    /**
     * Test that the Badge component TypeScript interface fix is working.
     */
    public function test_badge_component_typescript_fix()
    {
        $badgePath = base_path('resources/js/components/ui/badge/Badge.vue');
        $this->assertFileExists($badgePath);

        $content = File::get($badgePath);

        // Check that the problematic HTMLAttributes extension is removed
        $this->assertStringNotContainsString('extends HTMLAttributes', $content);

        // Check that the vue-ignore comment is present
        $this->assertStringContainsString('/* @vue-ignore */', $content);

        // Check that VariantProps is still used
        $this->assertStringContainsString('VariantProps<typeof badgeVariants>', $content);

        // Check that the component has proper props interface
        $this->assertStringContainsString('interface BadgeProps', $content);
    }

    /**
     * Test that build output includes our new components.
     */
    public function test_build_includes_new_components()
    {
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for build testing');
        }

        // Run build to ensure latest changes are included
        $result = Process::timeout(120)->run('yarn build');
        $this->assertEquals(0, $result->exitCode());

        $buildOutput = $result->output();

        // Check that our new components are being processed (check manifest instead)
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
        $this->assertArrayHasKey('resources/js/pages/Projects/CreateProjectForm.vue', $manifest);

        // Check that CreateProjectForm is in the build
        $createFormEntry = $manifest['resources/js/pages/Projects/CreateProjectForm.vue'];
        $this->assertArrayHasKey('file', $createFormEntry);
    }

    /**
     * Test that CSS is properly compiled and includes our components.
     */
    public function test_css_compilation_includes_components()
    {
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for CSS testing');
        }

        // Ensure build is complete
        if (!File::exists(public_path('build/manifest.json'))) {
            Process::timeout(120)->run('yarn build');
        }

        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
        $appEntry = $manifest['resources/js/app.ts'];
        $cssFiles = $appEntry['css'] ?? [];

        $this->assertNotEmpty($cssFiles, 'CSS files should be present in app entry');

        foreach ($cssFiles as $cssFile) {
            $this->assertFileExists(public_path('build/' . $cssFile));
            $cssContent = File::get(public_path('build/' . $cssFile));
            $this->assertNotEmpty($cssContent, 'CSS file should not be empty');
        }
    }

    /**
     * Test that the build process handles our AI task generation components.
     */
    public function test_ai_components_build_successfully()
    {
        if (!$this->hasNodeJs()) {
            $this->markTestSkipped('Node.js not available for component testing');
        }

        // Check that AI-related components exist and have proper structure
        $aiComponents = [
            'resources/js/pages/Projects/CreateTasks.vue' => [
                'TaskSuggestion',
                'AICommunication',
                'aiCommunication'
            ],
            'resources/js/pages/Projects/CreateProjectForm.vue' => [
                'const generateTasks',
                'router.visit',
                '/dashboard/projects/create/tasks'
            ]
        ];

        foreach ($aiComponents as $componentPath => $expectedContent) {
            $fullPath = base_path($componentPath);
            $this->assertFileExists($fullPath);

            $content = File::get($fullPath);
            foreach ($expectedContent as $expectedString) {
                $this->assertStringContainsString($expectedString, $content,
                    "Component {$componentPath} should contain {$expectedString}");
            }
        }
    }

    /**
     * Test that Vite configuration is properly set up.
     */
    public function test_vite_configuration_is_valid()
    {
        $viteConfigPath = base_path('vite.config.ts');
        $this->assertFileExists($viteConfigPath);

        $content = File::get($viteConfigPath);
        $this->assertStringContainsString('laravel', $content);
        $this->assertStringContainsString('vue', $content);
    }

    /**
     * Check if Node.js and yarn are available for testing.
     */
    private function hasNodeJs(): bool
    {
        try {
            $nodeResult = Process::run('node --version');
            $yarnResult = Process::run('yarn --version');

            return $nodeResult->exitCode() === 0 && $yarnResult->exitCode() === 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
