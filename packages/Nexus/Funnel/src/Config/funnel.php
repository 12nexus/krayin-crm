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
];
