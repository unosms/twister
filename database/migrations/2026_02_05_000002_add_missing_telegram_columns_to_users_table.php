<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_enabled')) {
                $table->boolean('telegram_enabled')->default(false)->after('status');
            }
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 64)->nullable()->after('telegram_enabled');
            }
            if (!Schema::hasColumn('users', 'telegram_devices')) {
                $table->json('telegram_devices')->nullable()->after('telegram_chat_id');
            }
            if (!Schema::hasColumn('users', 'telegram_ports')) {
                $table->string('telegram_ports', 255)->nullable()->after('telegram_devices');
            }
            if (!Schema::hasColumn('users', 'telegram_severities')) {
                $table->json('telegram_severities')->nullable()->after('telegram_ports');
            }
            if (!Schema::hasColumn('users', 'telegram_event_types')) {
                $table->json('telegram_event_types')->nullable()->after('telegram_severities');
            }
            if (!Schema::hasColumn('users', 'telegram_template')) {
                $table->text('telegram_template')->nullable()->after('telegram_event_types');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'telegram_enabled',
                'telegram_chat_id',
                'telegram_devices',
                'telegram_ports',
                'telegram_severities',
                'telegram_event_types',
                'telegram_template',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
