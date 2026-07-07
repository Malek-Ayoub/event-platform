<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $venue_id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string $name
 * @property string $path
 * @property string|null $mime_type
 * @property int|null $size
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'documentable_type',
        'documentable_id',
        'name',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'documentable_type' => 'string',
            'documentable_id' => 'integer',
            'name' => 'string',
            'path' => 'string',
            'mime_type' => 'string',
            'size' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
