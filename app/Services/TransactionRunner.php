<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionRunner
{
    /**
     * Run the callback inside a database transaction.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     *
     * @throws Throwable
     */
    public function run(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
