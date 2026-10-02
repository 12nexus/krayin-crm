<?php

return [
    'panel' => [
        'meeting'           => 'Meeting',
        'no-meeting'        => 'No meeting scheduled yet.',
        'meeting-passed'    => 'This meeting time has passed. Mark whether it was held or a no-show.',
        'meeting-closed'    => 'This meeting is closed.',
        'archived'          => 'Invalid · archived',
        'valid'             => 'Valid lead',
        'mark-valid'        => 'Valid',
        'mark-invalid'      => 'Invalid',
        'held'              => 'Meeting held',
        'no-show'           => 'No show',
        'schedule'          => 'Schedule meeting',
        'reschedule'        => 'Reschedule meeting',
        'move-meeting'      => 'Change meeting time',
        'follow-up-meeting' => 'Schedule another meeting',
        'new-meeting'       => 'New meeting',
        'restore'           => 'Restore lead',
        'call'              => 'Call back',
        'no-call'           => 'No call back time set.',
        'call-passed'       => 'This call back time has passed. Call the client, then book a meeting or set a new time.',
        'call-closed'       => 'This call back is closed.',
        'set-call'          => 'Set call back time',
        'reschedule-call'   => 'Reschedule call',
    ],

    'call-modal' => [
        'hint' => 'Pick the new date and time the client asked to be called, in their own timezone. It is logged as a new Call entry and shown on the lead\'s card.',
        'note' => 'What changed (optional)',
        'save' => 'Save call time',
    ],

    'meeting-modal' => [
        'hint' => 'Enter the time in the client\'s own timezone. It is checked against every other sales meeting so two never overlap.',
        'save' => 'Save meeting',
        'new-hint' => 'For a client who came to their meeting and wants another. The current meeting is recorded as held, and the administrators are emailed to add the new one to the calendar.',
    ],

    'invalid-modal' => [
        'title'   => 'Mark lead as invalid',
        'hint'    => 'The lead is archived: it leaves the board and any open meeting is cancelled. It stays on record under the "Archived Leads" pipeline and can be restored.',
        'reason'  => 'Reason (optional)',
        'confirm' => 'Mark invalid',
    ],

    'flash' => [
        'valid'    => 'Lead marked as valid.',
        'invalid'  => 'Lead marked invalid and archived.',
        'restored' => 'Lead restored to New Lead.',
        'held'     => 'Meeting recorded as held. The lead is now in Follow Up.',
        'no-show'  => 'No-show recorded. The lead is now in No Show; reschedule when the client is ready.',
        'meeting'  => 'Meeting scheduled.',
        'new-meeting' => 'Follow-up meeting booked. The administrators have been emailed to add it to the calendar.',
        'call'     => 'Call back time saved.',
    ],

    'errors' => [
        'failed'            => 'That did not work, and nothing was changed. Please try again.',
        'not-invalidatable' => 'Only leads in New Lead, Meeting Scheduled or No Show can be marked invalid.',
        'archived'          => 'This lead is archived. Restore it before booking a meeting.',
        'new-meeting-stage' => 'A new meeting is booked once the client has come to a meeting: from Meeting Scheduled or Follow Up.',
        'call-new-only'     => 'A call back time is only set on leads in New Lead.',
        'meeting-from-lead' => 'Meetings are booked from the lead page (Schedule / Reschedule meeting) so the client\'s timezone is recorded and clashes are checked.',
    ],

    'export' => [
        'button' => 'Export CSV',
        'hint'   => 'Download every lead on this board, with its current stage, as a CSV',
    ],

    'copy' => [
        'copied' => 'Copied :value',
        'hint'   => 'Click to copy',
    ],
];
