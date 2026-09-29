<?php

/**
 * The shared sales mailbox, as a mailer of its own next to the CRM's default
 * (noreply) one. Merged into mail.mailers.
 */
return [
    'sales' => [
        'transport'   => env('FOLLOW_UP_MAIL_TRANSPORT', 'smtp'),
        'host'        => env('FOLLOW_UP_MAIL_HOST', 'mail.12nexusbpo.com'),
        'port'        => env('FOLLOW_UP_MAIL_PORT', 465),
        'encryption'  => env('FOLLOW_UP_MAIL_ENCRYPTION', 'ssl'),
        'username'    => env('FOLLOW_UP_MAIL_USERNAME', 'team@sales.12nexusbpo.com'),
        'password'    => env('FOLLOW_UP_MAIL_PASSWORD'),
        'timeout'     => 30,
    ],
];
