<?php

namespace App\Http\Requests\Settlements;

use App\Models\Venue;

class AdminSettlementVenueListRequest extends SettlementReadRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:name,gross_sales,commission_paid,outstanding,outstanding_commission,last_payment'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'min_outstanding' => ['sometimes', 'numeric', 'min:0'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return $value !== null ? (string) $value : null;
    }

    public function sort(): ?string
    {
        $value = $this->validated('sort');

        return $value !== null ? (string) $value : null;
    }

    public function direction(): string
    {
        return (string) ($this->validated('direction') ?? 'desc');
    }

    public function minOutstanding(): ?string
    {
        $value = $this->validated('min_outstanding');

        return $value !== null ? number_format((float) $value, 2, '.', '') : null;
    }
}
