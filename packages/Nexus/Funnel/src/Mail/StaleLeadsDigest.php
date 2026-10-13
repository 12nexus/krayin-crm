<?php

namespace Nexus\Funnel\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * The morning list of open leads that need chasing: overdue follow-ups and
 * rotten leads. Sent straight from the scheduled command, not queued, so a
 * failure shows in the command's exit status.
 *
 * With an $agentName it is that sales executive's own copy: only their leads,
 * addressed to them, without the owner column.
 */
class StaleLeadsDigest extends Mailable
{
    public function __construct(
        public Collection $overdue,
        public Collection $rotten,
        public Carbon $now,
        public string $timezone,
        public array $recipients,
        public ?string $agentName = null,
    ) {}

    public function envelope(): Envelope
    {
        $parts = [];

        if ($this->overdue->isNotEmpty()) {
            $parts[] = $this->overdue->count().' overdue follow-up'.($this->overdue->count() === 1 ? '' : 's');
        }

        if ($this->rotten->isNotEmpty()) {
            $parts[] = $this->rotten->count().' rotten lead'.($this->rotten->count() === 1 ? '' : 's');
        }

        return new Envelope(
            to: $this->recipients,
            subject: sprintf(
                ($this->agentName ? 'Your leads' : 'Leads').' to chase, %s: %s',
                $this->now->copy()->setTimezone($this->timezone)->format('D j M'),
                $parts ? implode(', ', $parts) : 'all clear',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'funnel::emails.stale-leads',
            with: [
                'overdue'   => $this->overdue,
                'rotten'    => $this->rotten,
                'now'       => $this->now,
                'zone'      => $this->timezone,
                'agentName' => $this->agentName,
                'leadUrl'   => fn (int $id) => rtrim(config('app.url'), '/').'/admin/leads/view/'.$id,
                'logoPath'  => base_path('packages/Nexus/SalesForm/src/Resources/assets/logo.png'),
            ],
        );
    }
}
