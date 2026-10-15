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
];
