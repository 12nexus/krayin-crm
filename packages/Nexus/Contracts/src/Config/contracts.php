<?php

return [
    /**
     * 12 Nexus as it appears in both documents.
     */
    'company' => [
        'name'      => '12 Nexus BPO',
        'address'   => '5615 RALEIGH ST, MISSISSAUGA, ON L5M 7E4',
        'signatory' => 'Sayyed Shozib Abbas',
        'title'     => 'CEO',
    ],

    /**
     * Starting values for a lead's fields where the lead itself has nothing.
     */
    'defaults' => [
        'client_title'   => 'Client / Realtor',
        'resources'      => 1,
        'days_per_week'  => 5,
        'monthly_charge' => 650,
        'setup_fee'      => 50,
        'duration'       => 12,
    ],

    /**
     * Working hours a day for each engagement type on the lead.
     */
    'hours_per_day' => [
        'Part-time' => 4,
        'Full-time' => 8,
    ],

    'plans' => ['Part time', 'Full time', 'Shared'],

    /**
     * Agreement duration choices, in months.
     */
    'durations' => [3, 6, 12, 18, 24],

    /**
     * Download file names; :name is the client name.
     */
    'documents' => [
        'nda'       => 'Non-Disclosure Agreement (NDA) - :name',
        'agreement' => 'Agreement to Hire Virtual Assistant - :name',
    ],
];
