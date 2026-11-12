<?php

namespace Nexus\SalesForm\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Asks for the CRM's Google Calendar account to be connected again, with the
 * steps. Sent by nexus:google-calendar-check, not queued, so a failure shows
 * in the command's exit status.
 *
 * $status: expired | missing | due (see CheckGoogleCalendar).
 */
class GoogleCalendarReauth extends Mailable
{
    public function __construct(
        public array $recipients,
        public string $status,
        public ?string $accountEmail,
        public ?string $expiredAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->recipients,
            subject: $this->status === 'due'
                ? 'Reminder: renew the CRM\'s Google Calendar sign-in'
                : 'Action needed: reconnect Google Calendar in the CRM',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'sales_form::emails.google-calendar-reauth',
            with: [
                'status'       => $this->status,
                'accountEmail' => $this->accountEmail,
                'expiredAt'    => $this->expiredAt,
                'settingsUrl'  => route('admin.settings.google_calendar.index'),
                'logoPath'     => base_path('packages/Nexus/SalesForm/src/Resources/assets/logo.png'),
            ],
        );
    }
}
