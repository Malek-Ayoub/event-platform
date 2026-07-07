<?php

namespace App\Exceptions;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public function __construct(string $message = 'Tenant context has not been resolved for this request.')
    {
        parent::__construct($message);
    }
}
