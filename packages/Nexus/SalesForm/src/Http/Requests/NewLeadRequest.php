<?php

namespace Nexus\SalesForm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The quick "Create Lead" form: phone and name identify the client, the sales
 * owner is always set, and everything else is optional.
 */
class NewLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('leads.create');
    }

    public function rules(): array
    {
        return [
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'phone'           => ['required', 'string', 'max:30'],
            'lead_name'       => ['required', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'note'            => ['nullable', 'string', 'max:5000'],
            'brokerage'       => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:255'],
            'state'           => ['nullable', 'string', 'max:255'],
            'engagement_type' => ['nullable', Rule::in(config('sales_form.engagement_types'))],
            'lead_value'      => ['nullable', 'numeric', 'min:'.config('sales_form.minimum_lead_value'), 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'         => 'Sales Owner',
            'lead_name'       => 'Name',
            'note'            => 'Additional Note',
            'engagement_type' => 'Part-time / Full-time',
            'lead_value'      => 'Estimated monthly value',
        ];
    }

    public function messages(): array
    {
        return [
            'lead_value.min' => 'The estimated monthly value cannot be below the $:min retainer.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! PhoneRule::isValid($this->input('phone'))) {
                $validator->errors()->add('phone', 'Enter a valid 10-digit phone number.');
            }
        });
    }
}
