<?php

namespace Nexus\Phone\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Nexus\Phone\Services\CallNotes;

/**
 * Gemini's notes for a call the phone just reported with a recording. Retried
 * twice (the phone may still be finishing the file, or Gemini be busy); after
 * that, `artisan nexus:call-notes` picks the call up again.
 */
class TranscribeCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $backoff = [120, 900];

    /**
     * Long calls take Gemini a few minutes.
     */
    public $timeout = 600;

    public function __construct(public string $callId) {}

    public function handle(CallNotes $callNotes): void
    {
        $callNotes->process($this->callId);
    }
}
