<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_permissions') || Schema::hasColumn('device_permissions', 'allowed_command_template_ids')) {
            return;
        }

        Schema::table('device_permissions', function (Blueprint $table) {
            $table->text('allowed_command_template_ids')->nullable()->after('allowed_ports');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('device_permissions') || !Schema::hasColumn('device_permissions', 'allowed_command_template_ids')) {
            return;
        }

        Schema::table('device_permissions', function (Blueprint $table) {
            $table->dropColumn('allowed_command_template_ids');
        });
    }
};
