<?php

namespace Nexus\FollowUp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nexus\FollowUp\Mail\IntroEmail;
use Webkul\Lead\Models\Lead;

/**
 * Step 1 of the follow-up sequence: the services flyer, sent to the client as
 * soon as their lead is in New Lead without a meeting.
 */
class SendIntroEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    const STEP = 'intro';

    public $tries = 3;

    public $backoff = 120;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        if (! config('follow_up.enabled')) {
            return;
        }

        $lead = Lead::with(['stage', 'person', 'user'])->find($this->leadId);

        // Booked a meeting (or moved on) in the meantime: the intro no longer fits.
        if (! $lead || ($lead->stage->code ?? null) !== config('follow_up.intro.stage')) {
            return;
        }

        $clientEmail = $this->clientEmail($lead);

        if (! $clientEmail) {
            Log::info('Intro email skipped: the client has no valid email address', ['lead_id' => $lead->id]);

            return;
        }

        // Claim the step first, so a retry or a second worker never sends twice.
        try {
            $rowId = DB::table('nexus_follow_up_emails')->insertGetId([
                'lead_id'    => $lead->id,
                'step'       => self::STEP,
                'recipient'  => $clientEmail,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $mail = new IntroEmail(
            $clientEmail,
            $lead->person->name ?? '',
            $lead->user?->name,
            $lead->user?->email,
        );

        try {
            Mail::mailer('sales')->send($mail);
        } catch (\Throwable $e) {
            DB::table('nexus_follow_up_emails')->where('id', $rowId)->delete();

            throw $e;
        }

        DB::table('nexus_follow_up_emails')->where('id', $rowId)->update([
            'sent_at'    => now(),
            'updated_at' => now(),
        ]);

        $this->logOnLead($lead, $clientEmail);

        Log::info('Intro email sent', ['lead_id' => $lead->id]);
    }

    protected function clientEmail(Lead $lead): ?string
    {
        $emails = $lead->person?->emails;

        if (is_string($emails)) {
            $emails = json_decode($emails, true);
        }

        $email = trim((string) ($emails[0]['value'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * A note in the lead's activity history, so the rep can see the client has
     * had the flyer.
     */
    protected function logOnLead(Lead $lead, string $clientEmail): void
    {
        // activities.user_id is required; an unassigned lead goes without the note.
        if (! $lead->user_id) {
            return;
        }

        $cc = implode(', ', config('follow_up.cc'));

        $activityId = DB::table('activities')->insertGetId([
            'title'      => 'Intro email sent',
            'type'       => 'note',
            'comment'    => sprintf(
                "Automated intro email sent to %s%s from %s.\nSubject: %s\nAttached: services flyer.",
                $clientEmail,
                $cc !== '' ? ' (cc '.$cc.')' : '',
                config('follow_up.from_address'),
                config('follow_up.intro.subject'),
            ),
            'is_done'    => 1,
            'user_id'    => $lead->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $lead->id, 'activity_id' => $activityId]);

        if ($lead->person_id) {
            DB::table('person_activities')->insert(['person_id' => $lead->person_id, 'activity_id' => $activityId]);
        }
    }
}
