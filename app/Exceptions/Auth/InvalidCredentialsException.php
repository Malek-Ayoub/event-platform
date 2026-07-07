<?php

namespace App\Exceptions\Auth;

use Illuminate\Auth\AuthenticationException;

class InvalidCredentialsException extends AuthenticationException
{
    public function __construct(string $message = 'Invalid credentials.')
    {
        parent::__construct($message);
    }
}
