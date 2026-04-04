<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use App\Models\ReportEntry;
use Illuminate\Database\QueryException;

class SyncOrderShippingExpenseAction
{
    public function handle(Order $order, ?int $actorUserId = null): void
    {
        $shippingFee = (float) ($order->shipping_fee ?? 0);
        $shouldPersist = ! $order->trashed()
            && (string) $order->shipping_type === 'delivery'
            && $shippingFee > 0;
        $entryAttributes = [
            'order_id' => $order->id,
            'category' => 'shipping_expense',
        ];

        $entry = ReportEntry::withTrashed()
            ->where($entryAttributes)
            ->first();

        if (! $shouldPersist) {
            if ($entry && ! $entry->trashed()) {
                $entry->delete();
            }

            return;
        }

        $payload = [
            'user_id' => $actorUserId ?? $order->user_id,
            'occurred_on' => $order->shipping_date?->format('Y-m-d') ?? now()->toDateString(),
            'description' => "Ongkir Order #{$order->id}",
            'amount_idr' => $shippingFee,
            'amount_rmb' => null,
            'exchange_rate' => null,
            'freight_idr' => null,
            'tracking_number' => null,
            'code' => null,
            'estimated_arrived_on' => null,
            'notes' => null,
        ];

        try {
            if (! $entry) {
                $entry = new ReportEntry($entryAttributes);
            }

            if ($entry->trashed()) {
                $entry->restore();
            }

            $entry->fill($payload);
            $entry->save();
        } catch (QueryException $exception) {
            if (! $this->isDuplicateOrderCategoryException($exception)) {
                throw $exception;
            }

            $entry = ReportEntry::withTrashed()
                ->where($entryAttributes)
                ->first();

            if (! $entry) {
                throw $exception;
            }

            if ($entry->trashed()) {
                $entry->restore();
            }

            $entry->fill($payload);
            $entry->save();
        }
    }

    private function isDuplicateOrderCategoryException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000' && $sqlState !== '23505') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'report_entries_order_id_category_unique')
            || str_contains($message, 'order_id')
            || str_contains($message, 'category');
    }
}
