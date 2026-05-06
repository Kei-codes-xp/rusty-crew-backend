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
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->date('date');
            $table->string('clock_in',  5)->nullable();   
            $table->string('clock_out', 5)->nullable();   
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->decimal('overtime', 5, 2)->default(0);
            $table->enum('status', ['On time', 'Late', 'Undertime', 'Absent'])->default('On time');
            $table->enum('method', ['QR', 'Manual'])->default('QR');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
