<?php

namespace App\Http\Requests\Settlements;

class OrganizerSettlementSummaryRequest extends SettlementReadRequest
{
    public function authorize(): bool
    {
        return $this->canViewOrganizerSettlement();
    }
}
