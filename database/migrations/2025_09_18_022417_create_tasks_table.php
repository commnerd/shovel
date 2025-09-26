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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For MySQL/MariaDB, we need to disable foreign key checks
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        Schema::dropIfExists('tasks');

        // Re-enable foreign key checks
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
