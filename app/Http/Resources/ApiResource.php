<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = 'data';
}
