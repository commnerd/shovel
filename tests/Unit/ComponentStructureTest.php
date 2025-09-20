<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComponentStructureTest extends TestCase
{
    /**
     * Test that the Badge component has the correct TypeScript interface structure.
     */
    public function test_badge_component_typescript_interface_structure()
    {
        $badgePath = base_path('resources/js/components/ui/badge/Badge.vue');
        $this->assertFileExists($badgePath, 'Badge component should exist');

        $content = File::get($badgePath);

        // Test that the problematic HTMLAttributes extension is removed
        $this->assertStringNotContainsString(
            'extends HTMLAttributes',
            $content,
            'Badge component should not extend HTMLAttributes directly'
        );

        // Test that the interface is properly defined without VariantProps extension
        $this->assertStringContainsString(
            'interface BadgeProps {',
            $content,
            'Badge component should have a proper interface definition'
        );

        // Test that variant prop is properly typed
        $this->assertStringContainsString(
            "variant?: 'default' | 'secondary' | 'destructive' | 'outline';",
            $content,
            'Badge component should have properly typed variant prop'
        );

        // Test that withDefaults is used properly
        $this->assertStringContainsString(
            'const props = withDefaults(defineProps<BadgeProps>(),',
            $content,
            'Badge component should use withDefaults for prop defaults'
        );

        // Test that class prop is defined
        $this->assertStringContainsString(
            'class?: string;',
            $content,
            'Badge component should have optional class prop'
        );

        // Test that the component uses defineProps correctly
        $this->assertStringContainsString(
            'defineProps<BadgeProps>()',
            $content,
            'Badge component should use defineProps with BadgeProps type'
        );
    }

    /**
     * Test that the Badge component has all required variant definitions.
     */
    public function test_badge_component_variants()
    {
        $badgePath = base_path('resources/js/components/ui/badge/Badge.vue');
        $content = File::get($badgePath);

        $expectedVariants = [
            'default',
            'secondary',
            'destructive',
            'outline',
        ];

        foreach ($expectedVariants as $variant) {
            $this->assertStringContainsString(
                $variant.':',
                $content,
                "Badge component should have {$variant} variant defined"
            );
        }

        // Test that defaultVariants is set
        $this->assertStringContainsString(
            'defaultVariants: {',
            $content,
            'Badge component should have defaultVariants defined'
        );

        $this->assertStringContainsString(
            "variant: 'default'",
            $content,
            'Badge component should have default variant as default'
        );
    }

    /**
     * Test that the Badge component template structure is correct.
     */
    public function test_badge_component_template_structure()
    {
        $badgePath = base_path('resources/js/components/ui/badge/Badge.vue');
        $content = File::get($badgePath);

        // Test template section exists
        $this->assertStringContainsString('<template>', $content);
        $this->assertStringContainsString('</template>', $content);

        // Test that the template uses the correct class binding
        $this->assertStringContainsString(
            ':class="cn(badgeVariants({ variant: props.variant }), props.class)"',
            $content,
            'Badge template should use proper class binding with cn utility and props'
        );

        // Test that slot is used
        $this->assertStringContainsString(
            '<slot />',
            $content,
            'Badge template should have slot for content'
        );

        // Test that it's wrapped in a div
        $this->assertStringContainsString(
            '<div',
            $content,
            'Badge template should use div wrapper'
        );
    }

    /**
     * Test that the Badge component imports are correct.
     */
    public function test_badge_component_imports()
    {
        $badgePath = base_path('resources/js/components/ui/badge/Badge.vue');
        $content = File::get($badgePath);

        $expectedImports = [
            'import { cva, type VariantProps } from \'class-variance-authority\';',
            'import { cn } from \'@/lib/utils\';',
        ];

        foreach ($expectedImports as $import) {
            $this->assertStringContainsString(
                $import,
                $content,
                "Badge component should have import: {$import}"
            );
        }

        // Test that HTMLAttributes import is removed
        $this->assertStringNotContainsString(
            'import type { HTMLAttributes }',
            $content,
            'Badge component should not import HTMLAttributes'
        );
    }

    /**
     * Test that the ScrollArea component has proper structure.
     */
    public function test_scroll_area_component_structure()
    {
        $scrollAreaPath = base_path('resources/js/components/ui/scroll-area/ScrollArea.vue');
        $this->assertFileExists($scrollAreaPath, 'ScrollArea component should exist');

        $content = File::get($scrollAreaPath);

        // Test basic Vue component structure
        $this->assertStringContainsString('<script setup lang="ts">', $content);
        $this->assertStringContainsString('<template>', $content);
        $this->assertStringContainsString('</template>', $content);

        // Test that it uses radix-vue components
        $this->assertStringContainsString('from \'radix-vue\'', $content);
        $this->assertStringContainsString('ScrollAreaRoot', $content);
        $this->assertStringContainsString('ScrollAreaViewport', $content);
        $this->assertStringContainsString('ScrollAreaScrollbar', $content);
    }

    /**
     * Test that CreateTasks page has proper TypeScript structure.
     */
    public function test_create_tasks_page_typescript_structure()
    {
        $createTasksPath = base_path('resources/js/pages/Projects/CreateTasks.vue');
        $this->assertFileExists($createTasksPath, 'CreateTasks page should exist');

        $content = File::get($createTasksPath);

        // Test TypeScript interface definitions
        $this->assertStringContainsString(
            'interface TaskSuggestion',
            $content,
            'CreateTasks should have TaskSuggestion interface'
        );

        $this->assertStringContainsString(
            'interface AICommunication',
            $content,
            'CreateTasks should have AICommunication interface'
        );

        $this->assertStringContainsString(
            'interface Props',
            $content,
            'CreateTasks should have Props interface'
        );

        // Test that it uses proper Vue 3 Composition API
        $this->assertStringContainsString(
            'const props = defineProps<Props>()',
            $content,
            'CreateTasks should use defineProps with TypeScript'
        );
    }

    /**
     * Test that CreateProjectForm has proper AI integration structure.
     */
    public function test_create_project_form_ai_integration_structure()
    {
        $formPath = base_path('resources/js/pages/Projects/CreateProjectForm.vue');
        $this->assertFileExists($formPath, 'CreateProjectForm component should exist');

        $content = File::get($formPath);

        $this->assertStringContainsString(
            'router.visit',
            $content,
            'CreateProjectForm should use router.visit to navigate to task page'
        );

        // Test AI-related functions
        $this->assertStringContainsString(
            'const generateTasks = () =>',
            $content,
            'CreateProjectForm should have generateTasks function'
        );

        $this->assertStringContainsString(
            '/dashboard/projects/create/tasks',
            $content,
            'CreateProjectForm should redirect to task creation page'
        );

        // Test form handling
        $this->assertStringContainsString(
            'useForm',
            $content,
            'CreateProjectForm should use Inertia useForm'
        );
    }

    /**
     * Test that all component index files exist and export correctly.
     */
    public function test_component_index_exports()
    {
        $componentIndexes = [
            'resources/js/components/ui/badge/index.ts' => 'Badge',
            'resources/js/components/ui/scroll-area/index.ts' => 'ScrollArea',
        ];

        foreach ($componentIndexes as $indexPath => $expectedExport) {
            $fullPath = base_path($indexPath);
            $this->assertFileExists($fullPath, "Index file {$indexPath} should exist");

            $content = File::get($fullPath);
            $this->assertStringContainsString(
                "export { default as {$expectedExport} }",
                $content,
                "Index file {$indexPath} should export {$expectedExport}"
            );
        }
    }

    /**
     * Test that component files have proper Vue SFC structure.
     */
    public function test_vue_sfc_structure()
    {
        $vueComponents = [
            'resources/js/components/ui/badge/Badge.vue',
            'resources/js/components/ui/scroll-area/ScrollArea.vue',
            'resources/js/pages/Projects/CreateTasks.vue',
        ];

        foreach ($vueComponents as $componentPath) {
            $fullPath = base_path($componentPath);
            $this->assertFileExists($fullPath, "Component {$componentPath} should exist");

            $content = File::get($fullPath);

            // Test Vue SFC structure
            $this->assertStringContainsString(
                '<script setup lang="ts">',
                $content,
                "Component {$componentPath} should use TypeScript setup script"
            );

            $this->assertStringContainsString(
                '<template>',
                $content,
                "Component {$componentPath} should have template section"
            );

            // Test that it's valid Vue syntax (basic check)
            $this->assertStringContainsString(
                '</template>',
                $content,
                "Component {$componentPath} should have closing template tag"
            );
        }
    }
}
