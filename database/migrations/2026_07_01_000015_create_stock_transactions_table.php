<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('transaction_type', ['stock_in', 'stock_out', 'transfer_in', 'transfer_out', 'adjustment']);
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->string('reference_number', 100)->nullable();
            $table->foreignId('destination_branch_id')->nullable()->constrained('branches');
            $table->text('remarks')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
