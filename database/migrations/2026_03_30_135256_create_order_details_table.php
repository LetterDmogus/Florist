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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('item_type', ['bouquet', 'inventory_item'])->index();
            $table->integer('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->foreignId('bouquet_unit_id')->nullable()->constrained('bouquet_units');
            $table->foreignId('inventory_item_id')->nullable()->constrained('item_units');
            $table->decimal('money_bouquet', 10, 2)->nullable();
            $table->text('greeting_card')->nullable();
            $table->string('sender_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
