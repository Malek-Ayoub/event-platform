<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Venue;
use Illuminate\Validation\Rule;

class CreateVenueRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venue::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $excluded = config('tenancy.excluded_subdomains', []);

        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:255',
                'lowercase',
                'regex:/^[a-z0-9-]+$/',
                Rule::notIn($excluded),
                Rule::unique('venues', 'subdomain')->whereNull('deleted_at'),
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.not_in' => 'The subdomain is reserved and cannot be used.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain') && is_string($this->input('subdomain'))) {
            $this->merge([
                'subdomain' => strtolower(trim($this->input('subdomain'))),
            ]);
        }
    }
}
