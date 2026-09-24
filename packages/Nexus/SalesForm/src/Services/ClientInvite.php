<?php

namespace Nexus\SalesForm\Services;

/**
 * The description on the calendar invite for a discovery call.
 *
 * The client is a guest on that event and sees this text, so it is written
 * for them: what the call is, what 12NexusBPO does, and who to contact. Nothing
 * internal goes in here (call notes, willingness scores, CRM links); the team
 * has all of that in the CRM and in the new-lead email.
 *
 * The wording lives in the sales_form::app.invite translations.
 */
class ClientInvite
{
    public function description(?string $clientName, ?string $ownerName, ?string $ownerEmail): string
    {
        $firstName = trim(strtok(trim((string) $clientName), ' ') ?: '');
        $ownerName = trim((string) $ownerName);
        $ownerFirst = trim(strtok($ownerName, ' ') ?: '');

        $services = array_map(
            fn ($service) => '- '.$service,
            trans('sales_form::app.invite.services')
        );

        $contact = $ownerName !== ''
            ? trans('sales_form::app.invite.contact', [
                'name'  => $ownerName,
                'email' => $ownerEmail ? ' ('.$ownerEmail.')' : '',
            ])
            : null;

        return implode("\n", array_filter([
            $firstName !== ''
                ? trans('sales_form::app.invite.greeting', ['name' => $firstName])
                : trans('sales_form::app.invite.greeting-no-name'),
            '',
            trans('sales_form::app.invite.intro'),
            '',
            trans('sales_form::app.invite.what-we-do'),
            ...$services,
            '',
            trans('sales_form::app.invite.agenda'),
            '',
            trans('sales_form::app.invite.website', ['url' => config('sales_form.website_url')]),
            '',
            $contact,
            $ownerFirst !== ''
                ? trans('sales_form::app.invite.reschedule', ['name' => $ownerFirst])
                : trans('sales_form::app.invite.reschedule-no-name'),
        ], fn ($line) => $line !== null));
    }
}
