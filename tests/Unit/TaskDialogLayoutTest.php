<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class TaskDialogLayoutTest extends TestCase
{
    /**
     * Test that the TaskConfirmationDialog has improved width and layout settings.
     */
    public function test_task_confirmation_dialog_has_improved_width_settings()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $this->assertFileExists($dialogPath, 'TaskConfirmationDialog component should exist');

        $content = File::get($dialogPath);

        // Test that the dialog uses wider dimensions
        $this->assertStringContainsString(
            'max-w-6xl w-[90vw] max-h-[85vh]',
            $content,
            'Dialog should use wider dimensions for better content display'
        );

        // Test that scroll area has increased height
        $this->assertStringContainsString(
            'h-[500px]',
            $content,
            'ScrollArea should have increased height for more tasks'
        );
    }

    /**
     * Test that task cards have proper width classes for better layout.
     */
    public function test_task_cards_have_full_width_classes()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that task cards use full width
        $this->assertStringContainsString(
            'class="p-4 w-full"',
            $content,
            'Task cards should use full width for better layout'
        );

        // Test that task content areas use proper width classes
        $this->assertStringContainsString(
            'class="flex-1 min-w-0 w-full"',
            $content,
            'Task content areas should use full width classes'
        );
    }

    /**
     * Test that text content has proper wrapping and overflow handling.
     */
    public function test_text_content_has_proper_wrapping()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that task titles have break-words class
        $this->assertStringContainsString(
            'break-words',
            $content,
            'Task titles should have break-words class for proper text wrapping'
        );

        // Test that task descriptions have proper text wrapping
        $this->assertStringContainsString(
            'break-words whitespace-pre-wrap',
            $content,
            'Task descriptions should have proper text wrapping classes'
        );
    }

    /**
     * Test that the add new task section has proper width classes.
     */
    public function test_add_new_task_section_has_proper_layout()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that add new task card uses full width
        $this->assertStringContainsString(
            'class="p-4 border-dashed border-gray-300 w-full"',
            $content,
            'Add new task section should use full width'
        );
    }

    /**
     * Test that the dialog layout improvements maintain proper structure.
     */
    public function test_dialog_maintains_proper_structure()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that DialogContent has overflow-hidden for proper layout
        $this->assertStringContainsString(
            'overflow-hidden',
            $content,
            'Dialog should have overflow-hidden for proper layout'
        );

        // Test that flex layout is maintained
        $this->assertStringContainsString(
            'flex items-start gap-3 w-full',
            $content,
            'Task layout should maintain proper flex structure with full width'
        );
    }

    /**
     * Test that the dialog improvements don't break existing functionality.
     */
    public function test_dialog_improvements_maintain_existing_functionality()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that essential functionality is still present
        $essentialFeatures = [
            'editableTasks',
            'emit(\'confirm\'',
            ':data-testid="`suggested-task-',
            'data-testid="confirm-tasks"',
            'data-testid="cancel-tasks"',
            'data-testid="regenerate-tasks"',
            'toggleTaskStatus',
            'changePriority',
            'deleteTask',
            'addNewTask'
        ];

        foreach ($essentialFeatures as $feature) {
            $this->assertStringContainsString(
                $feature,
                $content,
                "Dialog should maintain essential functionality: {$feature}"
            );
        }
    }

    /**
     * Test that the component builds successfully with the layout improvements.
     */
    public function test_component_builds_successfully_with_improvements()
    {
        // Check that the build artifacts exist and are recent
        $manifestPath = public_path('build/manifest.json');

        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);

            // Check that CreateProjectForm (which uses TaskConfirmationDialog) is in the build
            $this->assertArrayHasKey('resources/js/pages/Projects/CreateProjectForm.vue', $manifest);

            $createFormEntry = $manifest['resources/js/pages/Projects/CreateProjectForm.vue'];
            $this->assertArrayHasKey('file', $createFormEntry);

            // Check that the built file exists
            $builtFile = public_path('build/' . $createFormEntry['file']);
            $this->assertFileExists($builtFile, 'Built CreateProjectForm file should exist');
        } else {
            $this->markTestSkipped('Build manifest not found - run yarn build to generate assets');
        }
    }

    /**
     * Test that the dialog width improvements are consistent across the component.
     */
    public function test_width_improvements_are_consistent()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Count occurrences of width-related classes to ensure consistency
        $widthClasses = [
            'w-full' => 4, // Should appear multiple times for cards and content areas
            'max-w-6xl' => 1, // Should appear once for dialog
            'w-[90vw]' => 1, // Should appear once for dialog
        ];

        foreach ($widthClasses as $class => $expectedMinCount) {
            $actualCount = substr_count($content, $class);
            $this->assertGreaterThanOrEqual(
                $expectedMinCount,
                $actualCount,
                "Class '{$class}' should appear at least {$expectedMinCount} times, found {$actualCount}"
            );
        }
    }

    /**
     * Test that responsive design considerations are properly implemented.
     */
    public function test_responsive_design_implementation()
    {
        $dialogPath = base_path('resources/js/components/TaskConfirmationDialog.vue');
        $content = File::get($dialogPath);

        // Test that both max-width and responsive width are used
        $this->assertStringContainsString(
            'max-w-6xl w-[90vw]',
            $content,
            'Dialog should use both max-width and responsive width for proper scaling'
        );

        // Test that height is also responsive
        $this->assertStringContainsString(
            'max-h-[85vh]',
            $content,
            'Dialog should use viewport-relative height for responsive design'
        );
    }
}
