<?php

/**
 * Under Leads, so admins (permission type "all") have it and other roles only
 * when it is ticked for them in Settings → Roles.
 */
return [
    [
        'key' => 'leads.contracts',
        'name' => 'contracts::app.acl.contracts',
        'route' => [
            'admin.leads.contracts.edit',
            'admin.leads.contracts.update',
            'admin.leads.contracts.download',
        ],
        'sort' => 5,
    ],
];
