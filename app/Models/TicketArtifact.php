<?php

namespace App\Models;

use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property TicketArtifactType $type
 * @property int $version
 * @property TicketArtifactStatus $status
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property string|null $checksum
 * @property \Illuminate\Support\Carbon $generated_at
 */
class TicketArtifact extends Model
{
    /** @use HasFactory<\Database\Factories\TicketArtifactFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'type',
        'version',
        'status',
        'disk',
        'path',
        'mime_type',
        'checksum',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'type' => TicketArtifactType::class,
            'version' => 'integer',
            'status' => TicketArtifactStatus::class,
            'disk' => 'string',
            'path' => 'string',
            'mime_type' => 'string',
            'checksum' => 'string',
            'generated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isReady(): bool
    {
        return $this->status === TicketArtifactStatus::Ready;
    }
}
