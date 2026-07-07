<?php

namespace App\Actions;

abstract class BaseAction
{
    abstract public function handle(mixed ...$arguments): mixed;
}
