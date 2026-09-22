<?php

namespace Nexus\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'company'          => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'address'          => ['nullable', 'string', 'max:2000'],
            'engagement_type'  => ['nullable', Rule::in(config('clients.engagement_types'))],
            'monthly_retainer' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'onboarded_on'     => ['nullable', 'date_format:Y-m-d'],
            'status'           => ['required', Rule::in(array_keys(config('clients.statuses')))],
            'notes'            => ['nullable', 'string', 'max:10000'],
            'user_id'          => ['nullable', 'integer', 'exists:users,id'],
            'lead_id'          => ['nullable', 'integer', 'exists:leads,id'],
            'person_id'        => ['nullable', 'integer', 'exists:persons,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'          => 'Account manager',
            'onboarded_on'     => 'Onboarded on',
            'monthly_retainer' => 'Monthly retainer',
            'engagement_type'  => 'Part-time / Full-time',
        ];
    }
}
