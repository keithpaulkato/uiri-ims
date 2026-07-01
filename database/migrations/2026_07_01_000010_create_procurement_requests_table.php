<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 50)->unique()->nullable();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('requested_date');
            $table->string('status', 30)->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_requests');
    }
};
