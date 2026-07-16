<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\BaseApiRequest;

class ShowPublicEventRequest extends BaseApiRequest
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
        return [
            'slug' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->route('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => $slug]);
        }
    }

    public function slug(): string
    {
        return (string) $this->validated('slug');
    }
}
