<?php

return [
    /**
     * The 12Nexus web phone (phone.apps.12nexusbpo.com). Empty URL turns the
     * CRM integration off: no dock, no call buttons.
     */
    'url' => rtrim((string) env('NEXUS_PHONE_URL', ''), '/'),

    /**
     * Shared with the phone (its KRAYIN_SECRET): signs the sign-in links the CRM
     * hands to the phone and the phone's calls back into the CRM.
     */
    'secret' => env('NEXUS_PHONE_SECRET', ''),

    /**
     * The number calls are made from, shown on the dock.
     */
    'number' => env('NEXUS_PHONE_NUMBER', '+1 (442) 221-1512'),

    /**
     * Seconds a signed request from the phone stays valid.
     */
    'max_skew' => 300,

    /**
     * Gemini transcribes each recorded call on a lead and adds its notes (summary,
     * key points, next steps, transcript) to the lead as a note. Off when false or
     * without GEMINI_API_KEY. Calls shorter than min_duration seconds are skipped.
     */
    'call_notes' => [
        'enabled'      => (bool) env('NEXUS_CALL_NOTES', true),
        'api_key'      => env('GEMINI_API_KEY', ''),
        'model'        => env('NEXUS_CALL_NOTES_MODEL', 'gemini-flash-latest'),
        'min_duration' => (int) env('NEXUS_CALL_NOTES_MIN_DURATION', 15),
    ],

    /**
     * The AI follow-up caller (in the web phone, /campaign.html): calls open New Leads with no
     * meeting in the last stale_days days (and created at least that long ago)
     * to book a discovery meeting. A lead is closed as Lost after max_attempts
     * calls nobody answered, at least retry_hours apart. Meetings are offered
     * on weekdays between meeting_hours in the client's zone, from lead_hours
     * ahead, over the next slot_days days. Each call's recording is copied from
     * the phone into the CRM's storage.
     */
    'ai' => [
        'user_email'    => env('NEXUS_AI_USER_EMAIL', 'ai-caller@12nexusbpo.com'),
        'stale_days'    => (int) env('NEXUS_AI_STALE_DAYS', 14),
        'max_attempts'  => (int) env('NEXUS_AI_MAX_ATTEMPTS', 6),
        'retry_hours'   => (int) env('NEXUS_AI_RETRY_HOURS', 20),
        'meeting_hours' => [env('NEXUS_AI_MEETING_FROM', '10:00'), env('NEXUS_AI_MEETING_TO', '17:00')],
        'lead_hours'    => (int) env('NEXUS_AI_LEAD_HOURS', 3),
        'slot_days'     => (int) env('NEXUS_AI_SLOT_DAYS', 7),
    ],
];
