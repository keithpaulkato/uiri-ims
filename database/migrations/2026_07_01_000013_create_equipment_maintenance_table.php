<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_name', 200);
            $table->string('equipment_type', 100);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->date('maintenance_date');
            $table->date('next_service_date')->nullable();
            $table->decimal('service_cost', 15, 2)->default(0.00);
            $table->string('status', 30)->default('Scheduled');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance');
    }
};
