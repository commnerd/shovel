<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Iteration assignment
            $table->foreignId('iteration_id')->nullable()->constrained()->nullOnDelete()->after('project_id');

            // T-shirt sizing for top-level tasks (epics)
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl'])->nullable()->after('status');

            // Story points for subtasks (fibonacci sequence)
            $table->integer('initial_story_points')->nullable()->after('size');
            $table->integer('current_story_points')->nullable()->after('initial_story_points');
            $table->integer('story_points_change_count')->default(0)->after('current_story_points');

            // Indexes for performance
            $table->index(['iteration_id']);
            $table->index(['size']);
            $table->index(['current_story_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['iteration_id']);
            $table->dropIndex(['size']);
            $table->dropIndex(['current_story_points']);

            // Drop foreign key constraint
            $table->dropForeign(['iteration_id']);

            // Drop the iteration_id column
            $table->dropColumn('iteration_id');

            // Drop other columns
            $table->dropColumn([
                'size',
                'initial_story_points',
                'current_story_points',
                'story_points_change_count'
            ]);
        });
    }
};
