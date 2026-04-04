<?php

declare(strict_types=1);

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
        $duplicateGroups = DB::table('report_entries')
            ->select('order_id', 'category', DB::raw('COUNT(*) as aggregate_count'))
            ->whereNotNull('order_id')
            ->groupBy('order_id', 'category')
            ->having('aggregate_count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $ids = DB::table('report_entries')
                ->where('order_id', $group->order_id)
                ->where('category', $group->category)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderByDesc('id')
                ->pluck('id');

            $keepId = $ids->shift();

            if ($keepId === null || $ids->isEmpty()) {
                continue;
            }

            DB::table('report_entries')
                ->whereIn('id', $ids->all())
                ->delete();
        }

        Schema::table('report_entries', function (Blueprint $table): void {
            $table->unique(['order_id', 'category'], 'report_entries_order_id_category_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_entries', function (Blueprint $table): void {
            $table->dropUnique('report_entries_order_id_category_unique');
        });
    }
};
