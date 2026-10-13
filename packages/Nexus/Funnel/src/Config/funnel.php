<?php

return [
    /**
     * The pipeline the sales funnel runs in. Its stages, in order:
     *
     *   New Lead → Meeting Scheduled → No Show → Follow Up → Won / Lost
     */
    'pipeline' => '12Nexus VA Sales',

    /**
     * Where invalid leads are archived: a separate pipeline, so they leave the
     * board but stay on record and can be restored.
     */
    'archive_pipeline' => 'Archived Leads',

    'archive_stage' => 'invalid',

    /**
     * Stages from which a lead can still be marked invalid.
     */
    'invalidatable' => ['new', 'meeting-scheduled', 'no-show'],

    /**
     * Stages offering "New meeting": the client came to their meeting and wants
     * another. From Meeting Scheduled that first records the meeting as held.
     */
    'new_meeting_stages' => ['meeting-scheduled', 'follow-up'],

    /**
     * Labels of the `lead_validity` select attribute.
     */
    'validity' => [
        'pending' => 'Not reviewed',
        'valid'   => 'Valid',
        'invalid' => 'Invalid',
    ],

    /**
     * Labels of the `meeting_appeared` select attribute.
     */
    'appeared' => [
        'pending'  => 'Pending',
        'attended' => 'Yes - Attended',
        'no_show'  => 'No - No Show',
    ],

    /**
     * The daily email of overdue follow-ups and rotten leads
     * (`nexus:stale-leads`), sent at 08:00 in `timezone` by the host's
     * krayin-stale-leads timer. Times in the email are shown in `timezone`.
     */
    'digest' => [
        'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FUNNEL_DIGEST_RECIPIENTS',
            'shehzer@12nexusbpo.com,shozib@12nexus.net'
        ))))),

        'timezone' => 'America/Los_Angeles',
    ],
];
