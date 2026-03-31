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
        Schema::create('report_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('occurred_on')->index();
            $table->enum('category', [
                'supply_income',
                'purchase_supply',
                'store_expense',
                'raw_material_expense',
                'shipping_expense',
                'refund',
                'profit_adjustment',
            ])->index();
            $table->string('description');
            $table->decimal('amount_idr', 14, 2)->default(0);
            $table->decimal('amount_rmb', 14, 2)->nullable();
            $table->decimal('exchange_rate', 14, 2)->nullable();
            $table->decimal('freight_idr', 14, 2)->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('code')->nullable();
            $table->date('estimated_arrived_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_entries');
    }
};
