<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            if (!Schema::hasColumn('app_settings', 'key')) {
                $table->string('key')->nullable()->after('id');
            }

            if (!Schema::hasColumn('app_settings', 'value')) {
                $table->text('value')->nullable()->after('key');
            }
        });

        if (Schema::hasColumn('app_settings', 'timezone') && Schema::hasColumn('app_settings', 'value')) {
            $existingTimezone = DB::table('app_settings')->whereNotNull('timezone')->value('timezone');
            if (is_string($existingTimezone) && $existingTimezone !== '') {
                $keyColumnExists = Schema::hasColumn('app_settings', 'key');
                if ($keyColumnExists) {
                    DB::table('app_settings')->updateOrInsert(
                        ['key' => 'timezone'],
                        ['value' => $existingTimezone]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('app_settings', 'value')) {
                $table->dropColumn('value');
            }
        });
    }
};
