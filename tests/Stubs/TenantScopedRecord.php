<?php

namespace Tests\Stubs;

use App\Support\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Model;

class TenantScopedRecord extends Model
{
    use BelongsToVenue;

    protected $table = 'tenant_scoped_records';

    protected $fillable = [
        'venue_id',
        'name',
    ];
}
