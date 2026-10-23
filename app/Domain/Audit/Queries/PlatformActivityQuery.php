<?php

namespace App\Domain\Audit\Queries;

use App\Domain\Audit\Data\PlatformActivityData;
use App\Domain\Audit\Models\PlatformAuditEvent;
use Illuminate\Support\Carbon;

class PlatformActivityQuery
{
    /**
     * The latest platform actions that concerned one school, newest first.
     *
     * @return list<PlatformActivityData>
     */
    public function forSchool(int $tenantId, int $limit): array
    {
        $rows = [];

        foreach (PlatformAuditEvent::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get() as $event) {
            /** @var array<string, mixed>|null $after */
            $after = $event->after;
            /** @var Carbon $occurredAt */
            $occurredAt = $event->occurred_at;

            $rows[] = new PlatformActivityData((string) $event->action, $after ?? [], $occurredAt->toIso8601String());
        }

        return $rows;
    }
}
