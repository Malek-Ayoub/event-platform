<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $venue_id
 * @property string $slug
 * @property string $subject
 * @property string $body
 * @property array<string, mixed>|null $variables
 * @property bool $is_active
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'slug',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'slug' => 'string',
            'subject' => 'string',
            'body' => 'string',
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
