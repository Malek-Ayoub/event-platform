<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AppliesArchitectureConstraints
{
    protected function driverName(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    /**
     * Soft-delete-safe unique index (blueprint v1.3 §50).
     *
     * @param  list<string>  $columns
     */
    protected function softDeleteSafeUnique(string $table, array $columns, string $indexName): void
    {
        $driver = $this->driverName();

        if ($driver === 'mysql') {
            $this->mysqlSoftDeleteSafeUnique($table, $columns, $indexName);

            return;
        }

        $columnList = implode(', ', $columns);

        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columnList}) WHERE deleted_at IS NULL");
        } elseif ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columnList}) WHERE deleted_at IS NULL");
        }
    }

    /**
     * @param  list<string>  $columns
     */
    protected function mysqlSoftDeleteSafeUnique(string $table, array $columns, string $indexName): void
    {
        $config = $this->mysqlSoftDeleteSafeUniqueConfig($indexName);

        if ($config === null) {
            $columnList = implode(', ', $columns);
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columnList})");

            return;
        }

        ['column' => $generatedColumn, 'expression' => $expression] = $config;

        if (! Schema::hasColumn($table, $generatedColumn)) {
            DB::statement("ALTER TABLE {$table} ADD {$generatedColumn} VARCHAR(255) AS ({$expression}) STORED");
        }

        DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$generatedColumn})");
    }

    /**
     * @return array{column: string, expression: string}|null
     */
    protected function mysqlSoftDeleteSafeUniqueConfig(string $indexName): ?array
    {
        return match ($indexName) {
            'venues_subdomain_unique' => [
                'column' => 'subdomain_active',
                'expression' => 'IF(deleted_at IS NULL, subdomain, NULL)',
            ],
            'venues_slug_unique' => [
                'column' => 'slug_active',
                'expression' => 'IF(deleted_at IS NULL, slug, NULL)',
            ],
            'events_venue_slug_unique' => [
                'column' => 'slug_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', slug), NULL)",
            ],
            'coupons_venue_code_unique' => [
                'column' => 'code_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', code), NULL)",
            ],
            'promo_codes_venue_code_unique' => [
                'column' => 'code_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', code), NULL)",
            ],
            default => null,
        };
    }

    protected function dropSoftDeleteSafeUnique(string $table, string $indexName): void
    {
        $driver = $this->driverName();

        if ($driver === 'mysql') {
            DB::statement("DROP INDEX {$indexName} ON {$table}");

            $config = $this->mysqlSoftDeleteSafeUniqueConfig($indexName);

            if ($config !== null && Schema::hasColumn($table, $config['column'])) {
                Schema::table($table, function (Blueprint $blueprint) use ($config): void {
                    $blueprint->dropColumn($config['column']);
                });
            }

            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$indexName}");
    }
}
