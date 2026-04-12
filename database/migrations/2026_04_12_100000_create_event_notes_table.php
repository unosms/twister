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
        if (Schema::hasTable('event_notes')) {
            return;
        }

        Schema::create('event_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->unsignedBigInteger('event_id');
            $table->text('note');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['source', 'event_id']);
            $table->index(['source', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('event_notes')) {
            return;
        }

        Schema::dropIfExists('event_notes');
    }
};
