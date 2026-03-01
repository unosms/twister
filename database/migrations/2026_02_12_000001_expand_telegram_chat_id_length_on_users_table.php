<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterTelegramChatIdLength(500);
    }

    public function down(): void
    {
        $this->alterTelegramChatIdLength(64);
    }

    private function alterTelegramChatIdLength(int $length): void
    {
        if (!Schema::hasColumn('users', 'telegram_chat_id')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            // SQLite does not enforce VARCHAR length in the same way; skip.
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `users` MODIFY `telegram_chat_id` VARCHAR({$length}) NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN telegram_chat_id TYPE VARCHAR({$length})");
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement("ALTER TABLE users ALTER COLUMN telegram_chat_id NVARCHAR({$length}) NULL");
        }
    }
};

