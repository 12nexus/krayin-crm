<?php

return [
    'statuses' => ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'],

    'engagement_types' => ['Part-time', 'Full-time'],

    'document_categories' => [
        'contract' => 'Contract with 12 Nexus',
        'other'    => 'Other document',
    ],

    /**
     * Uploads live on the private disk, outside the web root, and are only
     * served through an authenticated download route.
     */
    'disk' => 'local',

    'max_upload_kb' => 20480,

    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp'],

    'currency' => 'USD',

    'invoice_prefix' => 'INV',

    /**
     * Days after issue that a new invoice falls due, used as the form default.
     */
    'default_due_days' => 7,

    /**
     * Printed on the invoice PDF. Leave a line empty to omit it.
     */
    'issuer' => [
        'name'    => env('CLIENTS_INVOICE_ISSUER', '12NexusBPO'),
        'address' => env('CLIENTS_INVOICE_ADDRESS', ''),
        'email'   => env('CLIENTS_INVOICE_EMAIL', ''),
        'website' => env('CLIENTS_INVOICE_WEBSITE', 'https://12nexusbpo.com'),
    ],

    'payment_instructions' => env('CLIENTS_INVOICE_PAYMENT_INSTRUCTIONS', ''),
];
