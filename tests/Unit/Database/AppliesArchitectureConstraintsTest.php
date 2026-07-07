<?php

namespace Tests\Unit\Database;

use App\Support\Database\AppliesArchitectureConstraints;
use PHPUnit\Framework\TestCase;

class AppliesArchitectureConstraintsTest extends TestCase
{
    use AppliesArchitectureConstraints;

    public function test_venues_subdomain_mysql_generated_column_config(): void
    {
        $this->assertSame(
            [
                'column' => 'subdomain_active',
                'expression' => 'IF(deleted_at IS NULL, subdomain, NULL)',
            ],
            $this->mysqlSoftDeleteSafeUniqueConfig('venues_subdomain_unique'),
        );
    }

    public function test_venues_slug_mysql_generated_column_config(): void
    {
        $this->assertSame(
            [
                'column' => 'slug_active',
                'expression' => 'IF(deleted_at IS NULL, slug, NULL)',
            ],
            $this->mysqlSoftDeleteSafeUniqueConfig('venues_slug_unique'),
        );
    }

    public function test_events_venue_slug_mysql_generated_column_config(): void
    {
        $this->assertSame(
            [
                'column' => 'slug_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', slug), NULL)",
            ],
            $this->mysqlSoftDeleteSafeUniqueConfig('events_venue_slug_unique'),
        );
    }

    public function test_coupons_venue_code_mysql_generated_column_config(): void
    {
        $this->assertSame(
            [
                'column' => 'code_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', code), NULL)",
            ],
            $this->mysqlSoftDeleteSafeUniqueConfig('coupons_venue_code_unique'),
        );
    }

    public function test_promo_codes_venue_code_mysql_generated_column_config(): void
    {
        $this->assertSame(
            [
                'column' => 'code_active',
                'expression' => "IF(deleted_at IS NULL, CONCAT(venue_id, '-', code), NULL)",
            ],
            $this->mysqlSoftDeleteSafeUniqueConfig('promo_codes_venue_code_unique'),
        );
    }
}
