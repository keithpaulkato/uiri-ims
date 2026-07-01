<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('manager_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->text('description')->nullable();
            // section_manager_id / department_manager_id reference `users`,
            // which is created in a later migration (Task 4). Left as plain
            // nullable foreign id columns without ->constrained() to avoid
            // migration ordering failures, per task instructions.
            $table->foreignId('section_manager_id')->nullable();
            $table->foreignId('department_manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
