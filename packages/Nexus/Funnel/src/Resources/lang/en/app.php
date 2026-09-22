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
        'restore'           => 'Restore lead',
    ],

    'meeting-modal' => [
        'hint' => 'Enter the time in the client\'s own timezone. It is checked against every other sales meeting so two never overlap.',
        'save' => 'Save meeting',
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
    ],

    'errors' => [
        'failed'            => 'That did not work, and nothing was changed. Please try again.',
        'not-invalidatable' => 'Only leads in New Lead, Meeting Scheduled or No Show can be marked invalid.',
        'archived'          => 'This lead is archived. Restore it before booking a meeting.',
        'meeting-from-lead' => 'Meetings are booked from the lead page (Schedule / Reschedule meeting) so the client\'s timezone is recorded and clashes are checked.',
    ],

    'copy' => [
        'copied' => 'Copied :value',
        'hint'   => 'Click to copy',
    ],
];
