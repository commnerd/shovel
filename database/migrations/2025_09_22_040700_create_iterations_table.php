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
        Schema::create('iterations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
            $table->integer('capacity_points')->nullable(); // Total story points capacity for this iteration
            $table->integer('committed_points')->default(0); // Points committed to this iteration
            $table->integer('completed_points')->default(0); // Points completed in this iteration
            $table->integer('sort_order')->default(0);
            $table->json('goals')->nullable(); // JSON array of iteration goals
            $table->timestamps();

            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'sort_order']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, drop any foreign key constraints that reference this table
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'iteration_id')) {
                $table->dropForeign(['iteration_id']);
            }
        });

        // Then drop the table
        Schema::dropIfExists('iterations');
    }
};
