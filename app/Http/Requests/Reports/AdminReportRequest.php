<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Settlements\SettlementReadRequest;

class AdminReportRequest extends SettlementReadRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }

    public function limit(int $default = 10): int
    {
        return (int) ($this->validated('limit') ?? $default);
    }
}
