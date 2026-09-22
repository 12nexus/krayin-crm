<?php

return [
    'menu' => [
        'clients' => 'Onboarded Clients',
    ],

    'acl' => [
        'clients' => 'Onboarded Clients',
        'create'  => 'Create',
        'edit'    => 'Edit, documents and invoices',
        'delete'  => 'Delete',
    ],

    'index' => [
        'title'       => 'Onboarded Clients',
        'description' => 'Clients signed with 12 Nexus: their details, contract, documents and invoices.',
        'create'      => 'Add Client',
    ],

    'datagrid' => [
        'name'         => 'Client',
        'company'      => 'Company',
        'engagement'   => 'Part/Full-time',
        'retainer'     => 'Monthly retainer',
        'outstanding'  => 'Outstanding',
        'status'       => 'Status',
        'manager'      => 'Account manager',
        'onboarded-on' => 'Onboarded',
        'view'         => 'View',
        'edit'         => 'Edit',
        'delete'       => 'Delete',
    ],

    'fields' => [
        'name'         => 'Client name',
        'company'      => 'Company / brokerage',
        'email'        => 'Email',
        'phone'        => 'Phone',
        'address'      => 'Address',
        'engagement'   => 'Part-time / Full-time',
        'retainer'     => 'Monthly retainer (USD)',
        'onboarded-on' => 'Onboarded on',
        'status'       => 'Status',
        'manager'      => 'Account manager',
        'notes'        => 'Notes',
    ],

    'form' => [
        'create-title'      => 'Add Client',
        'edit-title'        => 'Edit :name',
        'from-lead'         => 'Prefilled from the won lead. Check the details before saving.',
        'already-onboarded' => 'This lead has already been onboarded as a client.',
        'client-info'       => 'Client information',
        'engagement'        => 'Engagement',
        'save'              => 'Save Client',
        'cancel'            => 'Cancel',
    ],

    'view' => [
        'edit'         => 'Edit',
        'retainer'     => 'Monthly retainer',
        'outstanding'  => 'Outstanding',
        'paid'         => 'Paid to date',
        'overdue'      => 'Overdue invoices',
        'lead'         => 'Original lead',
        'contract'     => 'Contract with 12 Nexus',
        'no-contract'  => 'No contract uploaded yet.',
        'invoices'     => 'Invoices',
        'no-invoices'  => 'No invoices yet.',
        'documents'    => 'Documents',
        'no-documents' => 'No other documents yet.',
    ],

    'invoice' => [
        'number'                  => 'Invoice',
        'month'                   => 'Month',
        'amount'                  => 'Amount (USD)',
        'due-date'                => 'Due date',
        'description'             => 'Description',
        'description-placeholder' => 'Defaults to VA services for the month',
        'status'                  => 'Status',
        'actions'                 => 'Actions',
        'create'                  => 'Create Invoice',
        'mark-paid'               => 'Mark paid',
        'mark-void'               => 'Void',
        'mark-unpaid'             => 'Reopen',
        'delete'                  => 'Delete invoice',
        'confirm-delete'          => 'Delete invoice :number? This cannot be undone.',
        'statuses' => [
            'paid'    => 'Paid',
            'unpaid'  => 'Unpaid',
            'overdue' => 'Overdue',
            'void'    => 'Void',
        ],
    ],

    'document' => [
        'title'                => 'Title',
        'title-placeholder'    => 'e.g. ID proof, onboarding checklist',
        'contract-placeholder' => 'e.g. Service agreement 2026',
        'file'                 => 'File (PDF, Word, Excel, image; up to 20 MB)',
        'upload'               => 'Upload Document',
        'upload-contract'      => 'Upload Contract',
        'delete'               => 'Delete document',
        'confirm-delete'       => 'Delete ":title"? This cannot be undone.',
    ],

    'lead' => [
        'won'         => 'This lead is won.',
        'onboard'     => 'Onboard as Client',
        'onboarded'   => 'This lead is an onboarded client.',
        'view-client' => 'Open Client',
    ],

    'flash' => [
        'created'           => 'Client added.',
        'updated'           => 'Client updated.',
        'deleted'           => 'Client deleted.',
        'document-uploaded' => 'Document uploaded.',
        'document-deleted'  => 'Document deleted.',
        'invoice-created'   => 'Invoice :number created.',
        'invoice-paid'      => 'Invoice :number marked as paid.',
        'invoice-void'      => 'Invoice :number voided.',
        'invoice-unpaid'    => 'Invoice :number reopened.',
        'invoice-deleted'   => 'Invoice :number deleted.',
    ],
];
