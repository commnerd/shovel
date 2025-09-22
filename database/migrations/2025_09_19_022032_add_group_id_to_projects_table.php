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
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('set null');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip down migration to avoid SQLite issues with indexes and columns
        // This migration adds group_id field that is essential for the application
        // and should not be rolled back in normal circumstances
    }
};
