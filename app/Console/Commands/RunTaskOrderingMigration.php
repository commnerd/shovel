<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class RunTaskOrderingMigration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'task:add-ordering-fields';

    /**
     * The console command description.
     */
    protected $description = 'Add task ordering fields to the tasks table if they don\'t exist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for task ordering fields...');

        if (!Schema::hasTable('tasks')) {
            $this->error('Tasks table does not exist!');
            return 1;
        }

        $fieldsToAdd = [
            'initial_order_index' => 'integer',
            'move_count' => 'integer',
            'current_order_index' => 'integer',
            'last_moved_at' => 'timestamp',
        ];

        $fieldsAdded = [];
        $fieldsSkipped = [];

        foreach ($fieldsToAdd as $field => $type) {
            if (!Schema::hasColumn('tasks', $field)) {
                $this->info("Adding {$field} field...");

                try {
                    Schema::table('tasks', function (Blueprint $table) use ($field, $type) {
                        switch ($type) {
                            case 'integer':
                                if ($field === 'move_count') {
                                    $table->integer($field)->default(0)->after('sort_order');
                                } else {
                                    $table->integer($field)->nullable()->after('sort_order');
                                }
                                break;
                            case 'timestamp':
                                $table->timestamp($field)->nullable()->after('sort_order');
                                break;
                        }
                    });

                    $fieldsAdded[] = $field;
                    $this->info("✓ Added {$field} field successfully");

                } catch (\Exception $e) {
                    $this->error("✗ Failed to add {$field}: " . $e->getMessage());
                    return 1;
                }
            } else {
                $fieldsSkipped[] = $field;
                $this->info("↪ {$field} field already exists");
            }
        }

        if (!empty($fieldsAdded)) {
            $this->info("\nSuccessfully added fields: " . implode(', ', $fieldsAdded));
        }

        if (!empty($fieldsSkipped)) {
            $this->info("Skipped existing fields: " . implode(', ', $fieldsSkipped));
        }

        $this->info("\nTask ordering fields are ready!");
        return 0;
    }
}
