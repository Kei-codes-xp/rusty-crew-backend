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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('emergency_contact');
            $table->enum('role',   ['Barista', 'Cashier', 'Manager', 'Admin'])->default('Barista');
            $table->enum('status', ['Active', 'Inactive', 'Resigned'])->default('Active');
            $table->decimal('hourly_rate',    8, 2)->default(80.00);
            $table->boolean('is_salaried')->default(false);
            $table->decimal('monthly_salary', 10, 2)->default(0.00);
            $table->string('pin',      255)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('qr_token',  64)->unique()->nullable();
            $table->unsignedTinyInteger('leave_balance')->default(5);
            $table->string('avatar_color', 2)->default('0');
            $table->string('avatar_url',   500)->nullable();
            $table->string('display_name', 100)->nullable();
            $table->string('nickname',      60)->nullable();
            $table->text('bio')->nullable();
            $table->string('theme_color',   10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
