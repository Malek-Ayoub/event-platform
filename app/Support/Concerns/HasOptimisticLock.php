<?php

namespace App\Support\Concerns;

use App\Exceptions\StaleModelException;
use Illuminate\Database\Eloquent\Model;

trait HasOptimisticLock
{
    public function getVersionColumn(): string
    {
        return 'version';
    }

    public function getVersion(): int
    {
        return (int) $this->getAttribute($this->getVersionColumn());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWithVersion(array $attributes, ?int $expectedVersion = null): static
    {
        $versionColumn = $this->getVersionColumn();
        $currentVersion = $expectedVersion ?? $this->getVersion();
        $nextVersion = $currentVersion + 1;

        $attributes[$versionColumn] = $nextVersion;

        $affected = static::query()
            ->whereKey($this->getKey())
            ->where($versionColumn, $currentVersion)
            ->update($attributes);

        if ($affected === 0) {
            throw new StaleModelException(static::class, $this->getKey(), $currentVersion);
        }

        return $this->refresh();
    }
}
