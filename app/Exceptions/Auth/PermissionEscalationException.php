<?php

namespace App\Exceptions\Auth;

use Illuminate\Auth\Access\AuthorizationException;

class PermissionEscalationException extends AuthorizationException
{
    public function __construct(string $message = 'Only venue owners or super admins may grant or revoke permissions.')
    {
        parent::__construct($message);
    }
}
