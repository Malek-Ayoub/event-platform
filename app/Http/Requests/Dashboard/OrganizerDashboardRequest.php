<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Settlements\SettlementReadRequest;

class OrganizerDashboardRequest extends SettlementReadRequest
{
    public function authorize(): bool
    {
        return $this->canViewOrganizerSettlement();
    }
}
