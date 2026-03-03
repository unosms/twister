<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cabinets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('size_u')->default(42);
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();
        });

        Schema::create('cabinet_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained('cabinets')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedSmallInteger('start_u');
            $table->unsignedSmallInteger('height_u')->default(1);
            $table->string('face', 10)->default('front');
            $table->timestamps();

            $table->unique('device_id');
            $table->index(['cabinet_id', 'face']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_placements');
        Schema::dropIfExists('cabinets');
        Schema::dropIfExists('rooms');
    }
};
