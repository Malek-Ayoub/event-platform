<?php

namespace App\Http\Resources\Events;

use App\Http\Resources\ApiResource;
use App\Models\Event;
use Illuminate\Http\Request;

/** @mixin Event */
class EventResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'banner_url' => $this->banner_url,
            'gallery' => $this->gallery,
            'video_url' => $this->video_url,
            'dj_info' => $this->dj_info,
            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'end_datetime' => $this->end_datetime?->toIso8601String(),
            'status' => $this->status->value,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
