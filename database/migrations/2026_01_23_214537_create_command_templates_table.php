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
        Schema::create('command_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('device_group_id')->nullable()->constrained('device_groups')->nullOnDelete();
            $table->string('ui_type')->default('button');
            $table->json('payload_template')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('requires_2fa')->default(false);
            $table->boolean('log_execution')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_templates');
    }
};
