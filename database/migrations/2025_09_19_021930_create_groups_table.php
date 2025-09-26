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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->boolean('is_default')->default(false); // For the 'Everyone' group
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, drop any foreign key constraints that reference this table
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'group_id')) {
                $table->dropForeign(['group_id']);
            }
        });

        Schema::table('group_user', function (Blueprint $table) {
            if (Schema::hasColumn('group_user', 'group_id')) {
                $table->dropForeign(['group_id']);
            }
        });

        // Then drop the table
        Schema::dropIfExists('groups');
    }
};
