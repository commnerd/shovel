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
        Schema::create('daily_curations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('curation_date');
            $table->json('suggestions'); // Array of suggestion objects
            $table->text('summary')->nullable();
            $table->json('focus_areas')->nullable(); // Array of focus area strings
            $table->enum('ai_provider', ['cerebrus', 'openai', 'anthropic', 'gemini'])->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            // Ensure one curation per user per project per day
            $table->unique(['user_id', 'project_id', 'curation_date']);

            // Index for efficient queries
            $table->index(['user_id', 'curation_date']);
            $table->index(['curation_date', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_curations');
    }
};
