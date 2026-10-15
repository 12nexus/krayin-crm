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
];
