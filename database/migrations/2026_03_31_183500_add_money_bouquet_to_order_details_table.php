<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_details', 'money_bouquet')) {
            Schema::table('order_details', function (Blueprint $table): void {
                $table->decimal('money_bouquet', 10, 2)->nullable()->after('inventory_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_details', 'money_bouquet')) {
            Schema::table('order_details', function (Blueprint $table): void {
                $table->dropColumn('money_bouquet');
            });
        }
    }
};
