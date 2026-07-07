<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UserNotInVenueException extends HttpException
{
    public function __construct(string $message = 'User does not belong to this venue.')
    {
        parent::__construct(403, $message);
    }
}
