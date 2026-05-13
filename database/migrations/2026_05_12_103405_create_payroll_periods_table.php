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
        Schema::create('payroll_periods', function (Blueprint $table) {
             $table->id();
            $table->string('label');
            $table->enum('frequency', ['weekly', 'semi_monthly', 'monthly'])->default('weekly');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'locked', 'voided'])->default('draft');
            $table->foreignId('generated_by')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();
 
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_hours', 8,  2)->default(0);
            $table->decimal('total_ot',    8,  2)->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->text('notes')->nullable();
 
            $table->timestamps();
 
            $table->unique(['frequency', 'start_date', 'end_date'], 'unique_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
