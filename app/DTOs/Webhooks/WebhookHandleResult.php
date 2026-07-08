<?php

namespace App\DTOs\Webhooks;

use App\DTOs\BaseDTO;
use App\Enums\InfrastructureDomain\WebhookLogStatus;

readonly class WebhookHandleResult extends BaseDTO
{
    public function __construct(
        public WebhookLogStatus $status,
        public int $webhookLogId,
        public bool $duplicate = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: WebhookLogStatus::from((string) $data['status']),
            webhookLogId: (int) $data['webhook_log_id'],
            duplicate: (bool) ($data['duplicate'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'webhook_log_id' => $this->webhookLogId,
            'duplicate' => $this->duplicate,
        ];
    }
}
