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
        if (
            !Schema::hasTable('device_event_permissions')
            || Schema::hasColumn('device_event_permissions', 'allowed_interfaces')
        ) {
            return;
        }

        Schema::table('device_event_permissions', function (Blueprint $table): void {
            $table->string('allowed_interfaces', 500)->nullable()->after('granted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            !Schema::hasTable('device_event_permissions')
            || !Schema::hasColumn('device_event_permissions', 'allowed_interfaces')
        ) {
            return;
        }

        Schema::table('device_event_permissions', function (Blueprint $table): void {
            $table->dropColumn('allowed_interfaces');
        });
    }
};
