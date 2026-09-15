<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
        });

        // Backfill unit_price for existing order details from bouquet_units or item_units
        try {
            DB::statement("
                UPDATE order_details od
                LEFT JOIN bouquet_units bu ON od.bouquet_unit_id = bu.id
                SET od.unit_price = bu.price
                WHERE od.item_type = 'bouquet' AND od.unit_price IS NULL
            ");

            DB::statement("
                UPDATE order_details od
                LEFT JOIN item_units iu ON od.inventory_item_id = iu.id
                SET od.unit_price = iu.price
                WHERE od.item_type = 'inventory_item' AND od.unit_price IS NULL
            ");
        } catch (\Throwable $e) {
            // Ignore if tables or drivers have specific restrictions during migrate
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
