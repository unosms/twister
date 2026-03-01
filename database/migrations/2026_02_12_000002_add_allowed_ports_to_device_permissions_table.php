<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_permissions') || Schema::hasColumn('device_permissions', 'allowed_ports')) {
            return;
        }

        Schema::table('device_permissions', function (Blueprint $table) {
            $table->string('allowed_ports', 500)->nullable()->after('granted_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('device_permissions') || !Schema::hasColumn('device_permissions', 'allowed_ports')) {
            return;
        }

        Schema::table('device_permissions', function (Blueprint $table) {
            $table->dropColumn('allowed_ports');
        });
    }
};

