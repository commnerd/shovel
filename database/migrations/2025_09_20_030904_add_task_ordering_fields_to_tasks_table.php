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
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('initial_order_index')->nullable()->after('sort_order');
            $table->integer('move_count')->default(0)->after('initial_order_index');
            $table->integer('current_order_index')->nullable()->after('move_count');
            $table->timestamp('last_moved_at')->nullable()->after('current_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['initial_order_index', 'move_count', 'current_order_index', 'last_moved_at']);
        });
    }
};
