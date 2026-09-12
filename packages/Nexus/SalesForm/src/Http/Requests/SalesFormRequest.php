<?php

namespace Nexus\SalesForm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('sales_form');
    }

    public function rules(): array
    {
        return [
            'user_id'                => ['nullable', 'integer', 'exists:users,id'],
            'lead_name'              => ['required', 'string', 'max:255'],
            'experience_years'       => ['nullable', 'string', 'max:100'],
            'using_assistant'        => ['nullable', Rule::in(['Yes', 'No'])],
            'assistant_type'         => ['nullable', Rule::in(['None', 'Remote VA', 'In-house Assistant', 'Other'])],
            'willingness'            => ['nullable', 'integer', 'between:1,3'],
            'brokerage'              => ['nullable', 'string', 'max:255'],
            'city'                   => ['nullable', 'string', 'max:255'],
            'state'                  => ['required', 'string', 'max:255'],
            'phone'                  => ['required', 'string', 'max:30'],
            'email'                  => ['required', 'email', 'max:255'],
            'meeting_date'           => ['required', 'date_format:Y-m-d'],
            'meeting_time'           => ['required', 'date_format:H:i'],
            'timezone'               => ['required', Rule::in(array_keys(config('sales_form.timezones')))],
            'additional_information' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lead_name'              => 'Lead Name',
            'experience_years'       => 'Experience in Real Estate',
            'using_assistant'        => 'Currently using an Assistant?',
            'assistant_type'         => 'If using assistant',
            'willingness'            => 'Willingness to Hire a VA',
            'meeting_date'           => 'Meeting Date',
            'meeting_time'           => 'Time',
            'additional_information' => 'Additional Information',
        ];
    }

    /**
     * A 10-digit NANP number is what the ViciDial mirror is keyed on; anything
     * shorter cannot identify an agent.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }

            if (strlen($digits) !== 10) {
                $validator->errors()->add('phone', 'Enter a valid 10-digit phone number.');
            }
        });
    }
}
