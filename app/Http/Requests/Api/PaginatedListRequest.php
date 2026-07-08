<?php

namespace App\Http\Requests\Api;

class PaginatedListRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(int $default = 15): int
    {
        return (int) ($this->validated('per_page') ?? $default);
    }
}
