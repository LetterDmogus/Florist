<?php

declare(strict_types=1);

namespace App\Traits;

use Spatie\Activitylog\Models\Activity;

trait HasAuditTrail
{
    /**
     * Get the audit trail data for this model.
     */
    public function getAuditTrail(): array
    {
        return [
            'creator' => $this->getEventAudit('created'),
            'updater' => $this->getEventAudit('updated'),
            'remover' => $this->getEventAudit('deleted'),
            'history' => $this->getHistory(),
        ];
    }

    /**
     * Get the timeline of recent activities.
     */
    protected function getHistory(): \Illuminate\Support\Collection
    {
        return Activity::forSubject($this)
            ->with('causer')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Activity $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'causer_name' => $log->causer?->name ?? 'System',
                'time' => $log->created_at->toDateTimeString(),
                'properties' => $log->properties,
            ]);
    }

    /**
     * Get specific event audit info.
     */
    protected function getEventAudit(string $event): ?array
    {
        $query = Activity::forSubject($this)
            ->where('event', $event);

        // For created, take the first one. For others, take the latest.
        $log = ($event === 'created') 
            ? $query->orderBy('id', 'asc')->first() 
            : $query->orderBy('id', 'desc')->first();

        if (! $log || ! $log->causer) {
            // Fallback to model timestamps if log is missing
            if ($event === 'created') {
                return [
                    'name' => 'System / Migrated',
                    'time' => $this->created_at?->toDateTimeString(),
                ];
            }
            return null;
        }

        return [
            'name' => $log->causer->name,
            'time' => $log->created_at->toDateTimeString(),
        ];
    }
}
