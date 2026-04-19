<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setAllowedInterfacesColumnType(toText: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setAllowedInterfacesColumnType(toText: false);
    }

    private function setAllowedInterfacesColumnType(bool $toText): void
    {
        foreach (['device_graph_permissions', 'device_event_permissions'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'allowed_interfaces')) {
                continue;
            }

            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                if (!$toText) {
                    DB::statement("UPDATE `{$table}` SET `allowed_interfaces` = LEFT(`allowed_interfaces`, 500) WHERE CHAR_LENGTH(`allowed_interfaces`) > 500");
                }

                $targetType = $toText ? 'TEXT' : 'VARCHAR(500)';
                DB::statement("ALTER TABLE `{$table}` MODIFY `allowed_interfaces` {$targetType} NULL");
                continue;
            }

            if ($driver === 'pgsql') {
                if (!$toText) {
                    DB::statement("UPDATE {$table} SET allowed_interfaces = LEFT(allowed_interfaces, 500) WHERE LENGTH(allowed_interfaces) > 500");
                }

                $targetType = $toText ? 'TEXT' : 'VARCHAR(500)';
                DB::statement("ALTER TABLE {$table} ALTER COLUMN allowed_interfaces TYPE {$targetType}");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN allowed_interfaces DROP NOT NULL");
                continue;
            }

            if ($driver === 'sqlsrv') {
                if (!$toText) {
                    DB::statement("UPDATE [{$table}] SET [allowed_interfaces] = LEFT([allowed_interfaces], 500) WHERE LEN([allowed_interfaces]) > 500");
                }

                $targetType = $toText ? 'NVARCHAR(MAX)' : 'NVARCHAR(500)';
                DB::statement("ALTER TABLE [{$table}] ALTER COLUMN [allowed_interfaces] {$targetType} NULL");
                continue;
            }

            // SQLite treats VARCHAR as TEXT affinity and does not enforce length, so no change is needed.
        }
    }
};
