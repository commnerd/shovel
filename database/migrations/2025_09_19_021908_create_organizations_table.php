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
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');
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
        Schema::dropIfExists('organizations');
    }
};
