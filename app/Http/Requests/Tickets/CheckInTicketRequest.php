<?php

namespace App\Http\Requests\Tickets;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Api\BaseApiRequest;

class CheckInTicketRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        $venueId = app(TenantContextInterface::class)->requireVenueId();

        return $this->user()?->can('checkin.perform', $venueId) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'uuid'],
            'gate_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function qrToken(): string
    {
        return (string) $this->validated('qr_token');
    }

    public function gateId(): ?int
    {
        $gateId = $this->validated('gate_id');

        return $gateId === null ? null : (int) $gateId;
    }

    public function deviceId(): ?string
    {
        $deviceId = $this->validated('device_id');

        return is_string($deviceId) ? $deviceId : null;
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) ? $notes : null;
    }
}
