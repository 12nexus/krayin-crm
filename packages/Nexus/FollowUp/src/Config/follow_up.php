<?php

return [
    /**
     * Master switch for every automated email to clients. Off unless the .env
     * turns it on, so a deploy never starts emailing clients by itself.
     */
    'enabled' => env('FOLLOW_UP_ENABLED', false),

    /**
     * Client emails go out from the shared sales mailbox under the lead owner's
     * name ("Westley Cooper at 12Nexus BPO"), with replies going to the owner.
     */
    'from_address' => env('FOLLOW_UP_FROM_ADDRESS', 'team@sales.12nexusbpo.com'),

    'from_name' => ':name at 12Nexus BPO',

    'from_name_unassigned' => '12Nexus BPO',

    /**
     * Copied on every client email.
     */
    'cc' => array_filter(explode(',', env('FOLLOW_UP_CC', 'shehzer@12nexusbpo.com'))),

    /**
     * Step 1: sent as soon as a lead lands in this stage with no meeting booked.
     */
    'intro' => [
        'stage' => 'new',

        'subject' => 'Hire a Virtual Assistant | Schedule a Discovery Call',

        'attachment' => 'Resources/assets/12nexusbpo-virtual-assistant-services.jpg',
    ],

    /**
     * Shown in the signature. Kept in step with the website footer (lib/seo.ts
     * in the 12nexusbpo-website repo).
     */
    'signature' => [
        'company' => '12NexusBPO',
        'tagline' => 'Remote Operations for Real Estate Teams',
        'phone'   => '+1 512 842 0504',
        'phone_e164' => '+15128420504',
        'website' => 'https://12nexusbpo.com',
        'office'  => '5615 Raleigh St, Mississauga, ON L5M 7E4, Canada',
    ],
];
