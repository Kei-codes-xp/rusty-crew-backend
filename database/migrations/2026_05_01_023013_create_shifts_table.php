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
        Schema::create('shifts', function (Blueprint $table) {
             $table->id();
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['Morning', 'Afternoon', 'Evening', 'Off'])->default('Off');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
