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
        Schema::create('payroll_entries', function (Blueprint $table) {
 
            // ── Foreign keys ──────────────────────────────────────────────────
            $table->foreignId('payroll_period_id')
                  ->constrained('payroll_periods')
                  ->cascadeOnDelete();
 
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();
            $table->string('employee_first_name');
            $table->string('employee_last_name');
            $table->string('employee_role', 30);
            $table->boolean('is_salaried')->default(false);
            $table->decimal('hourly_rate_snapshot',    8, 2)->default(0);
            $table->decimal('monthly_salary_snapshot', 10, 2)->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);  
            $table->decimal('ot_hours',    8, 2)->default(0);  
            $table->decimal('base_pay',    10, 2)->default(0);
            $table->decimal('ot_pay',      10, 2)->default(0); 
            $table->decimal('deductions',  10, 2)->default(0); 
            $table->decimal('gross_pay',   10, 2)->default(0); 
            $table->decimal('net_pay',     10, 2)->default(0);
 
            $table->json('time_log_ids')->nullable();
 
            $table->json('daily_breakdown')->nullable();
 
            $table->enum('status', ['draft', 'locked', 'voided'])->default('draft');
 
            $table->text('remarks')->nullable();
 
            $table->timestamps();
 
            $table->unique(['payroll_period_id', 'employee_id'], 'unique_entry');
 
            $table->index('employee_id');
            $table->index('payroll_period_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
