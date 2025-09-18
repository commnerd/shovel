<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing projects
        $projects = \App\Models\Project::all();

        if ($projects->isEmpty()) {
            $this->command->warn('No projects found. Please create some projects first.');
            return;
        }

        foreach ($projects as $project) {
            // Create 3-5 top-level tasks per project
            $topLevelTasksCount = rand(3, 5);

            for ($i = 0; $i < $topLevelTasksCount; $i++) {
                $topLevelTask = \App\Models\Task::factory()->create([
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'sort_order' => $i,
                ]);

                // 60% chance to create subtasks for this top-level task
                if (rand(1, 100) <= 60) {
                    $subtasksCount = rand(1, 3);

                    for ($j = 0; $j < $subtasksCount; $j++) {
                        $subtask = \App\Models\Task::factory()->create([
                            'project_id' => $project->id,
                            'parent_id' => $topLevelTask->id,
                            'sort_order' => $j,
                        ]);

                        // 30% chance to create sub-subtasks (depth 2)
                        if (rand(1, 100) <= 30) {
                            $subSubtasksCount = rand(1, 2);

                            for ($k = 0; $k < $subSubtasksCount; $k++) {
                                \App\Models\Task::factory()->create([
                                    'project_id' => $project->id,
                                    'parent_id' => $subtask->id,
                                    'sort_order' => $k,
                                ]);
                            }
                        }
                    }
                }
            }

            // Create a few standalone leaf tasks (top-level tasks without children)
            $leafTasksCount = rand(2, 4);
            for ($i = 0; $i < $leafTasksCount; $i++) {
                \App\Models\Task::factory()->create([
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'sort_order' => $topLevelTasksCount + $i,
                ]);
            }
        }

        $this->command->info('Tasks seeded successfully!');
    }
}
