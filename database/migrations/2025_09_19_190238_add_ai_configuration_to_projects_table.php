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
            $table->string('ai_provider')->default('cerebrus')->after('status');
            $table->string('ai_model')->nullable()->after('ai_provider');
            $table->text('ai_api_key')->nullable()->after('ai_model');
            $table->string('ai_base_url')->nullable()->after('ai_api_key');
            $table->json('ai_config')->nullable()->after('ai_base_url')->comment('Additional AI provider configuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'ai_model',
                'ai_api_key',
                'ai_base_url',
                'ai_config',
            ]);
        });
    }
};
