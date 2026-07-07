<?php

namespace App\Exceptions;

use RuntimeException;

class StaleModelException extends RuntimeException
{
    public function __construct(
        string $modelClass,
        int|string|null $modelKey,
        int $expectedVersion,
    ) {
        parent::__construct(sprintf(
            'Optimistic lock conflict on [%s:%s] for version [%d].',
            $modelClass,
            (string) $modelKey,
            $expectedVersion,
        ));
    }
}
