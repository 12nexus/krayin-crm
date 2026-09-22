<?php

namespace Nexus\SalesForm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The meeting section of the sales form on its own, for reschedules and
 * follow-up meetings on an existing lead.
 */
class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('leads.edit');
    }

    public function rules(): array
    {
        return [
            'meeting_date'           => ['required', 'date_format:Y-m-d'],
            'meeting_time'           => ['required', 'date_format:H:i'],
            'timezone'               => ['required', Rule::in(array_keys(config('sales_form.timezones')))],
            'additional_information' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => MeetingSlotRule::check($this, $validator));
    }

    public function attributes(): array
    {
        return [
            'meeting_date'           => 'Meeting Date',
            'meeting_time'           => 'Time',
            'additional_information' => 'Additional Information',
        ];
    }
}
