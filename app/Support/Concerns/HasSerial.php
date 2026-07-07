<?php

namespace App\Support\Concerns;

trait HasSerial
{
    public function getSerialColumn(): string
    {
        return 'serial';
    }

    public function getSerial(): ?string
    {
        $serial = $this->getAttribute($this->getSerialColumn());

        return $serial !== null ? (string) $serial : null;
    }
}
