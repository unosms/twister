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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'password_reveal')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->text('password_reveal')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'password_reveal')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_reveal');
        });
    }
};
