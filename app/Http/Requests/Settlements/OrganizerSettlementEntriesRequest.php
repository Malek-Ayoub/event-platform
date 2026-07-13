<?php

namespace App\Http\Requests\Settlements;

class OrganizerSettlementEntriesRequest extends SettlementReadRequest
{
    public function authorize(): bool
    {
        return $this->canViewOrganizerSettlement();
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }
}
