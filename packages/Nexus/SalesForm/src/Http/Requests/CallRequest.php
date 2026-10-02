<?php

namespace Nexus\SalesForm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new time to call a New Lead back, from the lead view.
 */
class CallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('activities.create');
    }

    public function rules(): array
    {
        return CallTimeRule::rules(true) + [
            'call_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => CallTimeRule::check($this, $validator));
    }

    public function attributes(): array
    {
        return [
            'call_at'       => 'Call back at',
            'call_timezone' => 'Client timezone',
            'call_note'     => 'Notes',
        ];
    }
}
