<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps PayrollPeriod model → frontend PayrollPeriod interface:
 * {
 *   id, label, frequency, startDate, endDate,
 *   status, generatedAt, lockedAt,
 *   totalGross, totalHours, totalOT, entryCount,
 *   notes
 * }
 */
class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'frequency'   => $this->frequency,
            'startDate'   => $this->start_date->format('Y-m-d'),
            'endDate'     => $this->end_date->format('Y-m-d'),
            'status'      => $this->status,
            'generatedAt' => $this->generated_at?->toISOString(),
            'lockedAt'    => $this->locked_at?->toISOString(),
            'generatedBy' => $this->generated_by,
            'totalGross'  => (float) $this->total_gross,
            'totalHours'  => (float) $this->total_hours,
            'totalOT'     => (float) $this->total_ot,
            'entryCount'  => (int)   $this->entry_count,
            'notes'       => $this->notes,
        ];
    }
}