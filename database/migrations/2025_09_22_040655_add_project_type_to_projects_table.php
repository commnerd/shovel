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
            $table->enum('project_type', ['finite', 'iterative'])->default('iterative')->after('status');
            $table->integer('default_iteration_length_weeks')->nullable()->after('project_type');
            $table->boolean('auto_create_iterations')->default(false)->after('default_iteration_length_weeks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'default_iteration_length_weeks', 'auto_create_iterations']);
        });
    }
};
