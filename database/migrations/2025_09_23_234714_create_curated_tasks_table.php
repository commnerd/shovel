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
        Schema::create('curated_tasks', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship columns
            $table->string('curatable_type'); // Model type (e.g., 'App\Models\Task')
            $table->unsignedBigInteger('curatable_id'); // Model ID

            // Work date to determine if shown on Today's Tasks page
            $table->date('work_date');

            // User assignment
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');

            // Index tracking
            $table->integer('initial_index')->default(0);
            $table->integer('current_index')->default(0);
            $table->integer('moved_count')->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['curatable_type', 'curatable_id']);
            $table->index(['work_date', 'assigned_to']);
            $table->index(['assigned_to', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curated_tasks');
    }
};
