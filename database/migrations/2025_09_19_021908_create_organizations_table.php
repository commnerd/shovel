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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable(); // Email domain for the organization
            $table->text('address')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->boolean('is_default')->default(false); // For the 'None' organization
            $table->timestamps();

            $table->unique('domain');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, drop any foreign key constraints that reference this table
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'organization_id')) {
                $table->dropForeign(['organization_id']);
            }
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('user_invitations', 'organization_id')) {
                $table->dropForeign(['organization_id']);
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'organization_id')) {
                $table->dropForeign(['organization_id']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organization_id')) {
                $table->dropForeign(['organization_id']);
            }
        });

        // Then drop the table
        Schema::dropIfExists('organizations');
    }
};
