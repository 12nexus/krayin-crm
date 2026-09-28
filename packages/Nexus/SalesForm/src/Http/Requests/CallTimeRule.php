<?php

namespace Nexus\SalesForm\Http\Requests;

use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * A call-back time is entered at the client's local time and must still be
 * ahead: a time already gone would sit on the board as overdue from the start.
 */
class CallTimeRule
{
    public static function rules(bool $required): array
    {
        return [
            'call_at'       => [$required ? 'required' : 'nullable', 'date_format:Y-m-d H:i'],
            'call_timezone' => ['required_with:call_at', 'nullable', Rule::in(array_keys(config('sales_form.timezones')))],
        ];
    }

    public static function check($request, $validator): void
    {
        if ($validator->errors()->hasAny(['call_at', 'call_timezone']) || ! $request->filled('call_at')) {
            return;
        }

        $zone = config('sales_form.timezones')[$request->input('call_timezone')] ?? 'UTC';

        $at = Carbon::createFromFormat('Y-m-d H:i', $request->input('call_at'), $zone);

        if ($at->lt(now()->subMinutes(5))) {
            $validator->errors()->add('call_at', 'The call-back time has already passed in the client\'s timezone. Pick a time ahead.');
        }
    }
}
