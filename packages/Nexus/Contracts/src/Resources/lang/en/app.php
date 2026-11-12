<?php

return [
    'acl' => [
        'contracts' => 'Contracts (NDA and VA agreement)',
    ],

    'panel' => [
        'title'     => 'Contracts',
        'not-saved' => 'Not prepared yet',
        'saved'     => 'Saved :when by :name',
        'prepare'   => 'Prepare contracts',
        'edit'      => 'Edit fields',
        'nda'       => 'NDA (PDF)',
        'agreement' => 'VA Agreement (PDF)',
    ],

    'edit' => [
        'title'        => 'Contracts: :name',
        'back'         => 'Back to lead',
        'intro'        => 'These fields fill both documents. Change a field here and it changes everywhere it appears in the NDA and the Agreement to Hire Virtual Assistant.',
        'unsaved'      => 'Not saved yet. The fields below are filled from the lead; save them to create the documents.',
        'from-lead'    => 'Showing the lead\'s current details. Save to replace the saved fields, or go back to keep them.',
        'refill'       => 'Refill from lead',
        'discard'      => 'Keep saved fields',
        'save'         => 'Save',
        'downloads'    => 'Documents',
        'downloads-hint' => 'Built from the saved fields each time you download.',
        'printed'      => 'As printed',
        'client'       => 'Client',
        'agreement'    => 'Agreement terms',
        'additions'    => 'Additions for this client (optional)',
        'blank'        => 'Left blank in the document',
    ],

    'fields' => [
        'client_name'      => 'Client name',
        'client_company'   => 'Client company',
        'client_brokerage' => 'Brokerage',
        'client_title'     => 'Client title / designation',
        'agreement_date'   => 'Agreement date',
        'start_date'       => 'Start date',
        'duration'         => 'Duration',
        'resources'        => 'No. of resources',
        'hours_per_day'    => 'Working hours a day',
        'days_per_week'    => 'Days a week',
        'plan'             => 'Plan',
        'monthly_charge'   => 'Monthly charge (USD)',
        'setup_fee'        => 'One-time setup fee (USD)',
        'additional_responsibilities' => 'Additional responsibilities',
        'additional_terms'            => 'Additional notes / terms',
    ],

    'placeholders' => [
        'additional_responsibilities' => "One responsibility per line, e.g.\nManage the client's Zillow and Realtor.com listings.",
        'additional_terms'            => 'Anything else agreed with this client, printed as its own section at the end of the agreement.',
    ],

    'printed' => [
        'nda_party'       => 'NDA party',
        'nda_title'       => 'NDA title',
        'agreement_party' => 'Agreement party',
        'agreement_date'  => 'Made as of',
        'term'            => 'Term',
        'hours'           => 'Hours',
        'charges'         => 'Charges',
        'setup_fee'       => 'Setup fee',
    ],

    'flash' => [
        'saved'      => 'Contract fields saved. The NDA and the agreement are ready to download.',
        'save-first' => 'Save the contract fields first, then download the documents.',
    ],
];
