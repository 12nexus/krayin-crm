<?php

namespace Nexus\Funnel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Nexus\Funnel\Mail\StaleLeadsDigest;
use Nexus\Funnel\Services\StaleLeads;

/**
 * Emails the daily list of overdue follow-ups and rotten leads. Run once a day
 * by the host's krayin-stale-leads timer (08:00 Pacific).
 */
class SendStaleLeadsDigest extends Command
{
    protected $signature = 'nexus:stale-leads
                            {--to=* : Send to these addresses instead of funnel.digest.recipients}
                            {--dry-run : List what the email would contain without sending it}';

    protected $description = 'Email the overdue follow-ups and rotten leads in the sales pipeline';

    public function handle(StaleLeads $staleLeads): int
    {
        $now = now();

        $overdue = $staleLeads->overdueFollowUps($now);

        $rotten = $staleLeads->rottenLeads($now);

        $this->info("{$overdue->count()} overdue follow-ups, {$rotten->count()} rotten leads.");

        if ($this->option('dry-run')) {
            $this->table(
                ['Lead', 'Client', 'Owner', 'Stage', 'Follow-up', 'Due (UTC)', 'Days overdue'],
                $overdue->map(fn ($r) => [$r->lead_id, $r->client_name, $r->owner_name, $r->stage_name, $r->activity_type.': '.$r->activity_title, $r->due->format('Y-m-d H:i'), $r->days_overdue])
            );

            $this->table(
                ['Lead', 'Client', 'Owner', 'Stage', 'Created', 'Days rotten'],
                $rotten->map(fn ($r) => [$r->lead_id, $r->client_name, $r->owner_name, $r->stage_name, $r->created->format('Y-m-d'), $r->days_rotten])
            );

            return self::SUCCESS;
        }

        $mail = new StaleLeadsDigest($overdue, $rotten, $now, config('funnel.digest.timezone'));

        if ($to = $this->option('to')) {
            config(['funnel.digest.recipients' => $to]);
        }

        Mail::send($mail);

        $this->info('Sent to '.implode(', ', config('funnel.digest.recipients')).'.');

        return self::SUCCESS;
    }
}
