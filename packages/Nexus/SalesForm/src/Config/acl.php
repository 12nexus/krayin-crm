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
            'admin.sales_form.schedule',
            'admin.sales_form.schedule.store',
            'admin.sales_form.day_plan',
        ],
        'sort' => 2,
    ], [
        'key' => 'settings.other_settings.google_calendar',
        'name' => 'sales_form::app.acl.google-calendar',
        'route' => [
            'admin.settings.google_calendar.index',
            'admin.settings.google_calendar.connect',
            'admin.settings.google_calendar.callback',
            'admin.settings.google_calendar.disconnect',
        ],
        'sort' => 3,
    ],
];
