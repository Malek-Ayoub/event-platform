<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidApiClientException extends RuntimeException
{
    public function __construct(string $message = 'Invalid API client credentials.')
    {
        parent::__construct($message);
    }
}
