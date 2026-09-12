<?php

return [
    /**
     * Where the lead lands when the form is submitted. Falls back to Krayin's
     * default pipeline / first stage when the named ones are absent.
     */
    'pipeline' => '12Nexus VA Sales',

    'stage' => 'meeting-scheduled',

    /**
     * Lead source used depending on whether the phone matched the ViciDial list.
     */
    'source_matched' => 'ViciDial Outbound',

    'source_unmatched' => 'Meeting Scheduling Form',

    'lead_type' => 'New Business',

    /**
     * Placeholder forecast applied to every lead this form creates, in the
     * account currency. The real number is set per lead once the scope of work
     * and full-time vs part-time are known.
     */
    'placeholder_lead_value' => 500,

    /**
     * Days after the meeting used for the lead's expected close date.
     */
    'close_date_offset_days' => 14,

    /**
     * Every agent in the ViciDial list works for this brokerage.
     */
    'default_brokerage' => 'eXp Realty',

    /**
     * Form timezone label => IANA zone, used to store the meeting at the right
     * UTC instant. Labels say "Standard Time" but the zones observe DST, which
     * is what the reps actually mean.
     */
    'timezones' => [
        'EST (Eastern Standard Time)' => 'America/New_York',
        'CST (Central Standard Time)' => 'America/Chicago',
        'MT (Mountain Time)'          => 'America/Denver',
        'PST (Pacific Standard Time)' => 'America/Los_Angeles',
    ],

    'willingness' => [
        1 => '1 - Low',
        2 => '2 - Medium',
        3 => '3 - High',
    ],
];
