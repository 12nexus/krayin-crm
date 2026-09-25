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
        public string $kind = 'created',
        public ?string $bookedBy = null,
    ) {}

    /**
     * What happened, as the email says it. Every kind of email gets its own
     * subject so they can be told apart in an inbox at a glance:
     *
     *  - created, with a meeting:  a new lead from the Sales Form
     *  - created, no meeting:      a new lead from Create Lead
     *  - meeting:                  first meeting booked on an existing lead
     *  - rescheduled:              an existing meeting moved (or rebooked after a no-show)
     *  - follow-up:                another meeting for a lead already in Follow Up
     */
    public function headline(): string
    {
        return match ($this->kind) {
            'meeting'     => 'Meeting scheduled',
            'rescheduled' => 'Meeting rescheduled',
            'follow-up'   => 'Follow-up meeting booked',
            default       => $this->digest['has_meeting'] ? 'New lead + meeting' : 'New lead, no meeting yet',
        };
    }

    public function envelope(): Envelope
    {
        $client = $this->digest['client_name'] ?: 'Lead';

        $when = $this->digest['has_meeting'] ? ' · '.$this->meetingTime() : '';

        return new Envelope(
            to: [new Address($this->recipientEmail, $this->recipientName)],
            replyTo: $this->digest['owner_email']
                ? [new Address($this->digest['owner_email'], $this->digest['owner_name'])]
                : [],
            subject: sprintf('%s: %s%s (%s)', $this->headline(), $client, $when, $this->digest['owner_name']),
        );
    }

    /**
     * "Wed 30 Sep, 11:00 AM MT": the meeting in the client's own zone, short
     * enough for a subject line.
     */
    protected function meetingTime(): string
    {
        try {
            $local = \Carbon\Carbon::parse($this->digest['meeting_local'])->format('D j M, g:i A');
        } catch (\Throwable) {
            return '';
        }

        $zone = strtok((string) ($this->digest['timezone_label'] ?? ''), ' ') ?: '';

        return trim($local.' '.$zone);
    }

    public function content(): Content
    {
        return new Content(
            view: 'sales_form::emails.lead-created',
            with: [
                'd' => $this->digest,
                'calendarUrl' => $this->calendarUrl,
                'recipientName' => $this->recipientName,
                'kind' => $this->kind,
                'headline' => $this->headline(),
                'bookedBy' => $this->bookedBy,
                'logoPath' => dirname(__DIR__).'/Resources/assets/logo.png',
            ],
        );
    }
}
