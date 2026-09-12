<?php

namespace Nexus\SalesForm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so a slow or unreachable mail server can never delay or fail the lead
 * creation that triggered it.
 */
class LeadCreatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public array $digest,
        public ?string $calendarUrl,
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->digest['client_name'] ?: 'New lead';

        return new Envelope(
            to: [new Address($this->recipientEmail, $this->recipientName)],
            replyTo: $this->digest['owner_email']
                ? [new Address($this->digest['owner_email'], $this->digest['owner_name'])]
                : [],
            subject: sprintf('New lead: %s (%s)', $client, $this->digest['owner_name']),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'sales_form::emails.lead-created',
            with: [
                'd' => $this->digest,
                'calendarUrl' => $this->calendarUrl,
                'recipientName' => $this->recipientName,
                'logoPath' => dirname(__DIR__).'/Resources/assets/logo.png',
            ],
        );
    }
}
