<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Settlements\SettlementReadRequest;

class OrganizerReportRequest extends SettlementReadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'event_id' => ['sometimes', 'integer', 'min:1'],
        ]);
    }

    public function authorize(): bool
    {
        return $this->canViewOrganizerSettlement();
    }

    public function eventId(): ?int
    {
        $eventId = $this->validated('event_id');

        return $eventId !== null ? (int) $eventId : null;
    }
}
