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
        Schema::create('daily_weight_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('metric_date');
            $table->integer('total_story_points')->default(0);
            $table->integer('total_tasks_count')->default(0);
            $table->integer('signed_tasks_count')->default(0);
            $table->integer('unsigned_tasks_count')->default(0);
            $table->decimal('average_points_per_task', 8, 2)->default(0);
            $table->decimal('daily_velocity', 8, 2)->default(0);
            $table->json('project_breakdown')->nullable(); // Store points per project
            $table->json('size_breakdown')->nullable(); // Store points by size (xs, s, m, l, xl)
            $table->timestamps();

            // Ensure one record per user per day
            $table->unique(['user_id', 'metric_date']);

            // Indexes for performance
            $table->index(['user_id', 'metric_date']);
            $table->index('metric_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_weight_metrics');
    }
};
