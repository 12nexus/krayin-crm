<?php

namespace Nexus\Phone\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nexus\Phone\Services\CallNotes;
use Throwable;

/**
 * Gemini's notes for recorded calls that have none yet: older calls, or calls
 * whose TranscribeCall job gave up. New calls are transcribed by the job.
 */
class TranscribeCalls extends Command
{
    protected $signature = 'nexus:call-notes
                            {--days=7 : Calls that started in this many days up to now}
                            {--since= : Start date (Y-m-d, UTC) instead of --days}
                            {--call=* : Only these phone call ids}
                            {--lead=* : Only these lead ids}
                            {--force : Transcribe again calls that already have notes, replacing the note}
                            {--dry-run : List the calls without transcribing them}';

    protected $description = 'Transcribe recorded phone calls with Gemini and add their notes to the leads';

    public function handle(CallNotes $callNotes): int
    {
        $query = DB::table('nexus_phone_calls')->whereNotNull('recording_url')->orderBy('started_at');

        if ($ids = $this->option('call')) {
            $query->whereIn('call_id', $ids);
        } else {
            $query->where('started_at', '>=', $this->option('since')
                ? Carbon::parse($this->option('since'), 'UTC')->startOfDay()
                : now()->subDays((int) $this->option('days')));
        }

        if ($leads = $this->option('lead')) {
            $query->whereIn('lead_id', array_map('intval', $leads));
        }

        if (! $this->option('force')) {
            $query->whereNull('notes_activity_id');
        }

        $rows = [];

        foreach ($query->get() as $row) {
            $reason = $callNotes->skipReason($row);

            if ($this->option('dry-run') || $reason) {
                $result = ['status' => $reason ? 'skipped' : 'to do', 'activity_id' => null, 'reason' => $reason];
            } else {
                try {
                    $result = $callNotes->process($row->call_id, (bool) $this->option('force'));
                } catch (Throwable $e) {
                    $result = ['status' => 'failed', 'activity_id' => null, 'reason' => mb_substr($e->getMessage(), 0, 200)];
                }
            }

            $rows[] = [
                $row->call_id,
                $row->started_at.' UTC',
                $row->lead_id ? '#'.$row->lead_id : '',
                $row->duration.' s',
                $result['status'],
                $result['activity_id'] ?? '',
                $result['reason'] ?? '',
            ];
        }

        $this->info(count($rows).' recorded calls: '.collect($rows)->countBy(4)->map(fn ($n, $s) => "$n $s")->implode(', '));
        $this->table(['Call', 'Started', 'Lead', 'Length', 'Status', 'Note', 'Reason'], $rows);

        return collect($rows)->contains(4, 'failed') ? self::FAILURE : self::SUCCESS;
    }
}
