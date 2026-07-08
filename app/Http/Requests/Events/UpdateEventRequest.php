<?php

namespace App\Http\Requests\Events;

use App\DTOs\BaseDTO;
use App\DTOs\Events\UpdateEventDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateEventRequest extends BaseApiRequest
{
    use ResolvesRouteEvent;

    public function authorize(): bool
    {
        $event = $this->routeEvent();

        return $event !== null && ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateEventDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', $this->tenantExists('categories')],
            'description' => ['sometimes', 'nullable', 'string'],
            'banner_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'gallery' => ['sometimes', 'nullable', 'array'],
            'gallery.*' => ['string', 'max:2048'],
            'video_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'dj_info' => ['sometimes', 'nullable', 'array'],
            'start_datetime' => ['sometimes', 'nullable', 'date'],
            'end_datetime' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_datetime'],
        ];
    }
}
