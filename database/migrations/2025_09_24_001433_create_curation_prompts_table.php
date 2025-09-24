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
        Schema::create('curation_prompts', function (Blueprint $table) {
            $table->id();

            // User and project context
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');

            // The actual prompt text sent to AI
            $table->longText('prompt_text');

            // Additional context for debugging
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->boolean('is_organization_user')->default(false);
            $table->integer('task_count')->default(0); // Number of tasks in the prompt

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curation_prompts');
    }
};
