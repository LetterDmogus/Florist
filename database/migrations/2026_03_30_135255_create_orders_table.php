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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('total', 10, 2);
            $table->date('shipping_date');
            $table->time('shipping_time');
            $table->enum('shipping_type', ['delivery', 'pickup'])->index();
            $table->decimal('down_payment', 10, 2)->nullable();
            $table->enum('payment_status', ['unpaid', 'dp', 'paid'])->default('unpaid')->index();
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'ready', 'on_delivery', 'completed'])
                ->default('pending')
                ->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shipping_date', 'shipping_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
