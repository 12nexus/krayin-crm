<?php

namespace Nexus\SalesForm\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Nexus\SalesForm\Services\MeetingNotes;

/**
 * Puts the Gemini notes, recordings and transcripts Google Meet attached to
 * each sales meeting's calendar event onto the lead, as a note with the links.
 * Run once a day by the host's krayin-meeting-notes timer, over the last week,
 * so files that reach the event late are still picked up.
 */
class SyncMeetingNotes extends Command
{
    protected $signature = 'nexus:meeting-notes
                            {--days=7 : Meetings that started in this many days up to now}
                            {--since= : Start date (Y-m-d, UTC) instead of --days, e.g. to sync every past meeting}
                            {--lead=* : Only these lead ids}
                            {--dry-run : Report what would be synced without writing anything}
                            {--csv= : Also write the report to this CSV file}';

    protected $description = 'Add the Google Meet notes and recordings of past sales meetings to their leads';

    public function handle(MeetingNotes $notes): int
    {
        $from = $this->option('since')
            ? Carbon::parse($this->option('since'), 'UTC')->startOfDay()
            : now()->subDays((int) $this->option('days'));

        $report = $notes->sync($from, now(), array_map('intval', $this->option('lead')), (bool) $this->option('dry-run'));

        $rows = collect($report['rows'])->map(fn ($row) => [
            $row['status'],
            $row['when']->format('Y-m-d H:i').' UTC',
            '#'.$row['lead_id'].' '.$row['client'],
            $row['owner'] ?? '',
            $row['meeting'],
            implode(', ', $row['files']),
            $row['matched'] ?? '',
            $row['reason'] ?? '',
        ]);

        $orphans = collect($report['orphans'])->map(fn ($row) => [
            $row['status'],
            $row['when']->format('Y-m-d H:i').' UTC',
            $row['lead_id'] ? '#'.$row['lead_id'] : '',
            $row['owner'] ?? '',
            $row['event'],
            implode(', ', $row['files']),
            $row['matched'] ?? '',
            $row['reason'] ?? '',
        ]);

        $headers = ['Status', 'Meeting (UTC)', 'Lead', 'Agent', 'Meeting / event', 'Files', 'Matched by', 'Reason'];

        $this->info('CRM meetings since '.$from->format('Y-m-d H:i').' UTC: '.$rows->countBy(0)->map(fn ($n, $s) => "$n $s")->implode(', '));
        $this->table($headers, $rows);

        if ($orphans->isNotEmpty()) {
            $this->info('Calendar events with notes or recordings and no CRM meeting:');
            $this->table($headers, $orphans);
        }

        if ($path = $this->option('csv')) {
            $handle = fopen($path, 'w');
            fputcsv($handle, array_merge(['Source'], $headers));

            foreach ($rows as $row) {
                fputcsv($handle, array_merge(['CRM meeting'], $row));
            }

            foreach ($orphans as $row) {
                fputcsv($handle, array_merge(['Calendar event only'], $row));
            }

            fclose($handle);
        }

        return self::SUCCESS;
    }
}
