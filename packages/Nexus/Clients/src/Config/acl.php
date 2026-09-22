<?php

return [
    [
        'key' => 'clients',
        'name' => 'clients::app.acl.clients',
        'route' => [
            'admin.clients.index',
            'admin.clients.view',
            'admin.clients.documents.download',
            'admin.clients.invoices.pdf',
        ],
        'sort' => 3,
    ], [
        'key' => 'clients.create',
        'name' => 'clients::app.acl.create',
        'route' => ['admin.clients.create', 'admin.clients.store'],
        'sort' => 1,
    ], [
        'key' => 'clients.edit',
        'name' => 'clients::app.acl.edit',
        'route' => [
            'admin.clients.edit',
            'admin.clients.update',
            'admin.clients.documents.store',
            'admin.clients.documents.delete',
            'admin.clients.invoices.store',
            'admin.clients.invoices.update',
            'admin.clients.invoices.delete',
        ],
        'sort' => 2,
    ], [
        'key' => 'clients.delete',
        'name' => 'clients::app.acl.delete',
        'route' => ['admin.clients.delete'],
        'sort' => 3,
    ],
];
