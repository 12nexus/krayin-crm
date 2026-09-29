<?php

namespace Nexus\FollowUp\Listeners;

use Illuminate\Support\Facades\Log;
use Nexus\FollowUp\Jobs\SendIntroEmail;

class QueueIntroEmail
{
    /**
     * Queue the intro email for a lead that has just landed in New Lead with no
     * meeting booked. The job itself decides whether to send, once the request
     * that created the lead has finished filling it in.
     *
     * Never throws: an email problem must not fail the lead creation.
     */
    public function handle($lead): void
    {
        if (! config('follow_up.enabled')) {
            return;
        }

        try {
            $lead->loadMissing('stage');

            if (($lead->stage->code ?? null) !== config('follow_up.intro.stage')) {
                return;
            }

            SendIntroEmail::dispatch($lead->id)->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Could not queue the intro email', [
                'lead_id' => $lead->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
