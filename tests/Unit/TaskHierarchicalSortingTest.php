<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\TasksController;
use Illuminate\Support\Collection;

class TaskHierarchicalSortingTest extends TestCase
{
    public function test_hierarchical_sorting_respects_sort_order_within_levels()
    {
        // Create a mock task controller to access the private method
        $controller = new TasksController();

        // Create test tasks with mixed hierarchy and sort orders
        $tasks = collect([
            (object) ['id' => 1, 'parent_id' => null, 'sort_order' => 2, 'title' => 'Parent B'],
            (object) ['id' => 2, 'parent_id' => null, 'sort_order' => 1, 'title' => 'Parent A'],
            (object) ['id' => 3, 'parent_id' => 1, 'sort_order' => 2, 'title' => 'Child B1'],
            (object) ['id' => 4, 'parent_id' => 1, 'sort_order' => 1, 'title' => 'Child A1'],
            (object) ['id' => 5, 'parent_id' => 2, 'sort_order' => 1, 'title' => 'Child A2'],
        ]);

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sortTasksHierarchically');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $tasks);

        // Verify the order: Parent A (sort_order 1), Child A2, Parent B (sort_order 2), Child A1, Child B1
        $expectedOrder = [
            ['id' => 2, 'title' => 'Parent A'],  // First parent by sort_order
            ['id' => 5, 'title' => 'Child A2'],  // Its child
            ['id' => 1, 'title' => 'Parent B'],  // Second parent by sort_order
            ['id' => 4, 'title' => 'Child A1'],  // Its first child by sort_order
            ['id' => 3, 'title' => 'Child B1'],  // Its second child by sort_order
        ];

        $this->assertCount(5, $result);

        foreach ($expectedOrder as $index => $expected) {
            $this->assertEquals($expected['id'], $result[$index]->id,
                "Task at position {$index} should be '{$expected['title']}' (ID: {$expected['id']})");
            $this->assertEquals($expected['title'], $result[$index]->title);
        }
    }

    public function test_hierarchical_sorting_handles_deep_nesting()
    {
        $controller = new TasksController();

        // Create deeply nested tasks
        $tasks = collect([
            (object) ['id' => 1, 'parent_id' => null, 'sort_order' => 1, 'title' => 'Root'],
            (object) ['id' => 2, 'parent_id' => 1, 'sort_order' => 1, 'title' => 'Level 1'],
            (object) ['id' => 3, 'parent_id' => 2, 'sort_order' => 1, 'title' => 'Level 2'],
            (object) ['id' => 4, 'parent_id' => 3, 'sort_order' => 1, 'title' => 'Level 3'],
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sortTasksHierarchically');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $tasks);

        // Should maintain the deep hierarchy order
        $this->assertEquals(1, $result[0]->id); // Root
        $this->assertEquals(2, $result[1]->id); // Level 1
        $this->assertEquals(3, $result[2]->id); // Level 2
        $this->assertEquals(4, $result[3]->id); // Level 3
    }

    public function test_hierarchical_sorting_handles_multiple_children_with_different_sort_orders()
    {
        $controller = new TasksController();

        $tasks = collect([
            (object) ['id' => 1, 'parent_id' => null, 'sort_order' => 1, 'title' => 'Parent'],
            (object) ['id' => 2, 'parent_id' => 1, 'sort_order' => 3, 'title' => 'Child C'],
            (object) ['id' => 3, 'parent_id' => 1, 'sort_order' => 1, 'title' => 'Child A'],
            (object) ['id' => 4, 'parent_id' => 1, 'sort_order' => 2, 'title' => 'Child B'],
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sortTasksHierarchically');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $tasks);

        // Should be: Parent, Child A (sort 1), Child B (sort 2), Child C (sort 3)
        $this->assertEquals('Parent', $result[0]->title);
        $this->assertEquals('Child A', $result[1]->title);
        $this->assertEquals('Child B', $result[2]->title);
        $this->assertEquals('Child C', $result[3]->title);
    }
}
