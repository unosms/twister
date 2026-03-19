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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'can_view_assigned_device_events')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_view_assigned_device_events')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'can_view_assigned_device_events')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_view_assigned_device_events');
        });
    }
};
