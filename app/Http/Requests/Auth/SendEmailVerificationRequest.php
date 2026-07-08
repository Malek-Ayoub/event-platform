<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Api\BaseApiRequest;

class SendEmailVerificationRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
