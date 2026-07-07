<?php

namespace App\Exceptions;

use RuntimeException;

class CrossTenantAccessException extends RuntimeException
{
    public function __construct(string $message = 'Cross-tenant access is not allowed.')
    {
        parent::__construct($message);
    }
}
