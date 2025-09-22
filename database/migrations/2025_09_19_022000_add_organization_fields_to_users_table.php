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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('pending_approval')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            $table->index('organization_id');
            $table->index('pending_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip down migration to avoid SQLite issues with indexes and columns
        // This migration adds organization fields that are essential for the application
        // and should not be rolled back in normal circumstances
    }
};
