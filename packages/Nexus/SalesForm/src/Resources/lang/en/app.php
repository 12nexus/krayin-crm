<?php

return [
    'menu' => [
        'sales-form' => 'Sales Form',
    ],

    'acl' => [
        'sales-form' => 'Sales Meeting Form',
    ],

    'index' => [
        'title'       => 'Sales Meeting Scheduling Form',
        'description' => 'Log lead details and schedule a discovery meeting after a successful call. Submitting creates the lead in the CRM.',

        'lookup' => [
            'title'          => 'Lead Identification',
            'hint'           => 'Enter the phone number first — matching agents are pulled from the eXp list automatically.',
            'phone'          => 'Phone Number',
            'fetch'          => 'Fetch details',
            'refetch'        => 'Phone number changed — fetch details',
            'searching'      => 'Looking up…',
            'matched'        => 'Matched in the eXp agent list',
            'not-matched'    => 'No match in the eXp agent list',
            'not-matched-help' => 'Fill in the name, brokerage, city, state and email manually below.',
            'incomplete'     => 'Enter all 10 digits to look the number up.',
            'verified-name'  => 'Verified name',
            'verified-email' => 'Email on file',
            'location'       => 'Location on file',
            'license'        => 'License / specialties',
            'code'           => 'ViciDial code',
            'agents-on-file' => ':count agents on file',
            'clear'          => 'Clear',
        ],

        'lead' => [
            'title'            => 'Lead Details',
            'sales-executive'  => 'Sales Executive',
            'sales-executive-help' => 'You are logged in as this user; the lead will be assigned to you.',
            'lead-name'        => 'Lead Name',
            'experience'       => 'Experience in Real Estate (in years)',
            'using-assistant'  => 'Currently using an Assistant?',
            'assistant-type'   => 'If using assistant:',
            'willingness'      => 'Willingness to Hire a VA',
            'willingness-low'  => 'Low',
            'willingness-high' => 'High',
            'brokerage'        => 'Brokerage',
            'city'             => 'City',
            'state'            => 'State',
            'email'            => 'Email Address',
        ],

        'meeting' => [
            'title'      => 'Meeting Information',
            'date'       => 'Meeting Date',
            'time'       => 'Time',
            'timezone'   => 'Timezone',
            'additional' => 'Additional Information',
            'preview'    => 'Meeting will be stored as :utc UTC.',
        ],

        'submit'    => 'Create Lead',
        'autofilled' => 'auto-filled',
    ],

    'store' => [
        'success' => 'Lead created: :title',
        'failed'  => 'Could not create the lead. Nothing was saved — please check the details and try again.',
    ],
];
