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

    public function messages(): array
    {
        return [
            'lead_value.min' => 'The estimated monthly value cannot be below the $:min retainer.',
        ];
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
            'engagement_type'        => ['nullable', Rule::in(config('sales_form.engagement_types'))],
            'lead_value'             => ['nullable', 'numeric', 'min:'.config('sales_form.minimum_lead_value'), 'max:1000000'],
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
            'engagement_type'        => 'Part-time / Full-time',
            'lead_value'             => 'Estimated monthly value',
        ];
    }

    /**
     * A 10-digit NANP number is what the ViciDial mirror is keyed on; anything
     * shorter cannot identify an agent.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! PhoneRule::isValid($this->input('phone'))) {
                $validator->errors()->add('phone', 'Enter a valid 10-digit phone number.');
            }

            MeetingSlotRule::check($this, $validator);
        });
    }
}
