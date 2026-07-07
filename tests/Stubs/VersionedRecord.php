<?php

namespace Tests\Stubs;

use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Model;

class VersionedRecord extends Model
{
    use HasOptimisticLock;

    protected $table = 'versioned_records';

    protected $fillable = [
        'name',
        'version',
    ];
}
