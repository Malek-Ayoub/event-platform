<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class ApiResourceCollection extends ResourceCollection
{
    /**
     * @var string|null
     */
    public static $wrap = 'data';
}
