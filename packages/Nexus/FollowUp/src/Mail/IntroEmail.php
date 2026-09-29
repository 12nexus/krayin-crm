<?php

namespace Nexus\FollowUp\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The first email a client gets: who we are, the services flyer, and an
 * invitation to reply and book a discovery call. Sent by SendIntroEmail, which
 * is already queued, so this one is not.
 */
class IntroEmail extends Mailable
{
    public function __construct(
        public string $clientEmail,
        public string $clientName,
        public ?string $ownerName,
        public ?string $ownerEmail,
    ) {}

    public function envelope(): Envelope
    {
        $ownerName = trim((string) $this->ownerName);

        $hasOwnerEmail = filter_var($this->ownerEmail, FILTER_VALIDATE_EMAIL) !== false;

        return new Envelope(
            from: new Address(
                config('follow_up.from_address'),
                $ownerName !== ''
                    ? str_replace(':name', $ownerName, config('follow_up.from_name'))
                    : config('follow_up.from_name_unassigned'),
            ),
            to: [new Address($this->clientEmail, $this->clientName ?: null)],
            cc: $this->cc(),
            replyTo: $hasOwnerEmail ? [new Address($this->ownerEmail, $ownerName ?: null)] : [],
            subject: config('follow_up.intro.subject'),
        );
    }

    /**
     * The configured CC list plus the lead owner, so the rep has the email in
     * their own inbox and knows what the client was sent.
     */
    public function cc(): array
    {
        $cc = config('follow_up.cc');

        if (filter_var($this->ownerEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $cc[] = $this->ownerEmail;
        }

        return array_values(array_unique(array_map('strtolower', $cc)));
    }

    public function content(): Content
    {
        $firstName = trim(strtok(trim($this->clientName), ' ') ?: '');

        return new Content(
            view: 'follow_up::emails.intro',
            text: 'follow_up::emails.intro-text',
            with: [
                'firstName'  => $firstName,
                'ownerName'  => trim((string) $this->ownerName),
                'ownerEmail' => $this->ownerEmail,
                'signature'  => config('follow_up.signature'),
                'logoPath'   => dirname(__DIR__).'/Resources/assets/logo.png',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(dirname(__DIR__).'/'.config('follow_up.intro.attachment'))
                ->as('12NexusBPO Virtual Assistant Services.jpg')
                ->withMime('image/jpeg'),
        ];
    }
}
