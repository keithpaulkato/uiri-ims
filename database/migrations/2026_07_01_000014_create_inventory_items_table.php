<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('section_id')->nullable()->constrained('sections');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('item_code', 50)->unique();
            $table->string('asset_code', 100)->nullable();
            $table->string('qr_code', 100)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('piece');
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->integer('current_stock')->default(0);
            $table->integer('minimum_stock')->default(5);
            $table->string('asset_type', 50)->default('Consumable');
            $table->date('purchase_date')->nullable();
            $table->date('warranty_date')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
