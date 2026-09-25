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
     * Stage the quick "Create Lead" form drops a lead into: a client who sounded
     * interested but has no meeting booked yet.
     */
    'new_lead_stage' => 'new',

    /**
     * Every client starts on a retainer of at least this much a month, in the
     * account currency. It is the default estimated lead value and the floor
     * the forms accept; a rep raises it to suit the deal.
     */
    'minimum_lead_value' => 600,

    /**
     * Whether the client is after a part-time or a full-time VA. Shown on the
     * kanban card in place of the price.
     */
    'engagement_types' => ['Part-time', 'Full-time'],

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

    /**
     * Notify administrators whenever a lead is created, wherever it came from.
     */
    'notify' => [
        'enabled' => env('SALES_FORM_NOTIFY', true),

        /**
         * Used when the administrator lookup returns nothing or fails outright,
         * so a lead is never created silently.
         */
        'fallback_email' => env('SALES_FORM_NOTIFY_FALLBACK', 'shehzer@12nexusbpo.com'),
        'fallback_name'  => 'Sayyed Shehzer Abbas',

        'meeting_minutes'  => 30,
        'meeting_location' => 'Online / phone',

        /**
         * Google Calendar id the "Add to Google Calendar" button targets, so the
         * recipient does not have to choose one. Found under Calendar settings ->
         * the calendar -> Integrate calendar -> Calendar ID. Looks like
         * c_xxxxxxxx@group.calendar.google.com for a shared calendar.
         *
         * Leave empty to let each recipient pick their own calendar.
         */
        'calendar_id' => env('SALES_FORM_CALENDAR_ID'),
    ],

    /**
     * The shared sales calendar's private iCal address ("Secret address in iCal
     * format" in Google Calendar's settings). Shown as the day plan beside the
     * meeting fields. It is a password for the calendar: keep it in .env only.
     */
    'calendar_feed' => [
        'url'           => env('SALES_FORM_ICAL_URL'),
        'cache_seconds' => 120,
    ],

    /**
     * OAuth client (Google Cloud → Google Auth Platform → Clients, type "Web
     * application") the CRM uses to book meetings straight onto the Sales Team
     * calendar as the connected account. Secrets: .env only.
     */
    'google' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    /**
     * Linked from the calendar invite the client receives.
     */
    'website_url' => 'https://12nexusbpo.com',

    'willingness' => [
        1 => '1 - Low',
        2 => '2 - Medium',
        3 => '3 - High',
    ],
];
