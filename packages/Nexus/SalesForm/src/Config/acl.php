<?php

return [
    [
        'key' => 'sales_form',
        'name' => 'sales_form::app.acl.sales-form',
        'route' => [
            'admin.sales_form.index',
            'admin.sales_form.lookup',
            'admin.sales_form.store',
            'admin.sales_form.activity_calendar',
        ],
        'sort' => 2,
    ],
];
