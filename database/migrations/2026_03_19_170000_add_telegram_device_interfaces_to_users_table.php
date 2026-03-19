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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'telegram_device_interfaces')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->json('telegram_device_interfaces')->nullable()->after('telegram_devices');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'telegram_device_interfaces')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('telegram_device_interfaces');
        });
    }
};
