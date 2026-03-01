<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('command_templates', 'script_name')) {
                $table->string('script_name')->nullable()->after('action_key');
            }
            if (!Schema::hasColumn('command_templates', 'script_code')) {
                $table->longText('script_code')->nullable()->after('script_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('command_templates', function (Blueprint $table) {
            if (Schema::hasColumn('command_templates', 'script_code')) {
                $table->dropColumn('script_code');
            }
            if (Schema::hasColumn('command_templates', 'script_name')) {
                $table->dropColumn('script_name');
            }
        });
    }
};
