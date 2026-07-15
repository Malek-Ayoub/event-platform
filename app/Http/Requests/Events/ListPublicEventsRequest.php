<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\PaginatedListRequest;
use Illuminate\Validation\Rule;

class ListPublicEventsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', Rule::in(['starts_at'])],
        ]);
    }

    public function sort(string $default = 'starts_at'): string
    {
        return (string) ($this->validated('sort') ?? $default);
    }
}
