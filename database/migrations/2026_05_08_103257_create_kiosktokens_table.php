<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('kiosk_tokens', function (Blueprint $table) {
            $table->id();

            // The random UUID encoded in the QR image
            $table->string('token', 64)->unique();

            // HMAC-SHA256 signature of (token + expires_at) using APP_KEY
            $table->string('signature', 128);

            // Device identifier of the kiosk that requested this token
            $table->string('kiosk_device_id', 100)->index();

            // Token validity window
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();

            // Consumed state — set when an employee successfully scans
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();

            // The attendance action that was recorded on scan
            $table->enum('action', ['clock_in', 'clock_out'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_tokens');
    }
};