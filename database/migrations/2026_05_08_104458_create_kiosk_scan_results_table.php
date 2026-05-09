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
        Schema::create('kiosk_scan_results', function (Blueprint $table) {
            $table->id();

            // The token that was scanned
            $table->string('token', 64)->index();

            // The kiosk device that displayed the QR
            $table->string('kiosk_device_id', 100)->index();

            // Employee who scanned
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            // Display fields pre-computed so kiosk doesn't need to join
            $table->string('employee_name');       // "John Doe"
            $table->string('action');              // 'clock_in' | 'clock_out'
            $table->string('formatted_time', 20);  // "08:41 AM"
            $table->string('avatar_color', 20);    // hex bg for initials

            $table->timestamp('scanned_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_scan_results');
    }
};
