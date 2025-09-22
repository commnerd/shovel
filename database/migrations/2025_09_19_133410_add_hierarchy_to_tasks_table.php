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
            // Add new hierarchy fields (parent_id and sort_order already exist)
            $table->integer('depth')->default(0)->after('parent_id');
            $table->string('path')->nullable()->after('depth'); // For efficient hierarchy queries
            $table->date('due_date')->nullable()->after('priority'); // Add due_date field

            // Add indexes for performance
            $table->index(['project_id', 'depth']);
            $table->index('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip down migration to avoid SQLite issues with indexes and columns
        // This migration adds hierarchy fields that are essential for the application
        // and should not be rolled back in normal circumstances
    }
};
