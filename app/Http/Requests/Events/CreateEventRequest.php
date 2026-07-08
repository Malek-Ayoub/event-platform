<?php

namespace App\Http\Requests\Events;

use App\DTOs\BaseDTO;
use App\DTOs\Events\CreateEventDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Event;

class CreateEventRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateEventDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'banner_url' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['string', 'max:2048'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'dj_info' => ['nullable', 'array'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after_or_equal:start_datetime'],
        ];
    }
}
