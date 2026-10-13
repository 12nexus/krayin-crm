<?php

namespace Nexus\Funnel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Nexus\Funnel\Mail\StaleLeadsDigest;
use Nexus\Funnel\Services\StaleLeads;

/**
 * Emails the daily list of overdue follow-ups and rotten leads: the full list
 * to funnel.digest.recipients, and to each active sales executive a copy with
 * only the leads they own. Run once a day by the host's krayin-stale-leads
 * timer (08:00 Pacific).
 */
class SendStaleLeadsDigest extends Command
{
    protected $signature = 'nexus:stale-leads
                            {--to=* : Deliver every email to these addresses instead (for testing)}
                            {--agent=* : Only these sales executives (email or user id)}
                            {--skip-agents : Send only the full list}
                            {--agents-only : Send only the sales executives\' copies}
                            {--dry-run : List what would be sent without sending it}';

    protected $description = 'Email the overdue follow-ups and rotten leads in the sales pipeline';

    public function handle(StaleLeads $staleLeads): int
    {
        $now = now();

        $zone = config('funnel.digest.timezone');

        $overdue = $staleLeads->overdueFollowUps($now);

        $rotten = $staleLeads->rottenLeads($now);

        $this->info("{$overdue->count()} overdue follow-ups, {$rotten->count()} rotten leads.");

        $messages = [];

        if (! $this->option('agents-only')) {
            $messages[] = new StaleLeadsDigest($overdue, $rotten, $now, $zone, config('funnel.digest.recipients'));
        }

        if (! $this->option('skip-agents')) {
            foreach ($this->agents() as $agent) {
                $ownOverdue = $overdue->where('owner_id', $agent->id)->values();
                $ownRotten = $rotten->where('owner_id', $agent->id)->values();

                if ($ownOverdue->isEmpty() && $ownRotten->isEmpty()) {
                    $this->line("{$agent->name}: nothing to chase, skipped.");

                    continue;
                }

                $messages[] = new StaleLeadsDigest($ownOverdue, $ownRotten, $now, $zone, [$agent->email], $agent->name);
            }
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['To', 'Overdue', 'Rotten'],
                collect($messages)->map(fn ($m) => [implode(', ', $m->recipients), $m->overdue->count(), $m->rotten->count()])
            );

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($messages as $message) {
            $intended = implode(', ', $message->recipients);

            if ($to = $this->option('to')) {
                $message->recipients = $to;
            }

            try {
                Mail::send($message);

                $this->info("Sent ({$intended}) to ".implode(', ', $message->recipients).'.');
            } catch (\Throwable $e) {
                $failed++;

                $this->error("Failed ({$intended}): {$e->getMessage()}");

                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Active users holding one of funnel.digest.agent_roles, narrowed by --agent.
     */
    protected function agents(): Collection
    {
        $only = $this->option('agent');

        return DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn('roles.name', config('funnel.digest.agent_roles'))
            ->where('users.status', 1)
            ->when($only, fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('users.email', $only)
                ->orWhereIn('users.id', array_filter($only, 'is_numeric'))))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);
    }
}
