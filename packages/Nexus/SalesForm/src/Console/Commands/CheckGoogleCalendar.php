<?php

namespace Nexus\SalesForm\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Nexus\SalesForm\Mail\GoogleCalendarReauth;
use Nexus\SalesForm\Services\GoogleCalendar;

/**
 * Checks that the Google account connected under Settings → Google Calendar
 * can still sign in, and when it cannot (or none is connected, or it is due
 * per sales_form.google.reauth_after_days) emails
 * sales_form.google.reauth_recipients the steps to reconnect it. Repeats at
 * most once a day until it is fixed. Run daily by the host's
 * krayin-google-calendar-check timer.
 */
class CheckGoogleCalendar extends Command
{
    const REMINDED = 'nexus.google_calendar.reauth_reminded_at';

    protected $signature = 'nexus:google-calendar-check
                            {--to=* : Send the email to these addresses instead (for testing)}
                            {--force : Email even if a reminder went out in the last day}
                            {--dry-run : Report the state without emailing}';

    protected $description = 'Check the CRM\'s Google Calendar sign-in and email a reconnect request when it has expired';

    public function handle(GoogleCalendar $calendar): int
    {
        if (! $calendar->configured()) {
            $this->info('The Google OAuth client is not configured; nothing to check.');

            return self::SUCCESS;
        }

        try {
            $status = $calendar->status();
        } catch (\Throwable $e) {
            // Google or the network having a bad moment is not an expired sign-in.
            $this->error('Could not check the Google sign-in: '.$e->getMessage());

            report($e);

            return self::FAILURE;
        }

        $account = $calendar->account();

        $days = (int) config('sales_form.google.reauth_after_days');

        if ($status === 'ok' && $days > 0 && Carbon::parse($account->created_at)->addDays($days)->isPast()) {
            $status = 'due';
        }

        $this->info(match ($status) {
            'ok'      => "Signed in as {$account->email}.",
            'due'     => "Signed in as {$account->email}, connected over {$days} days ago: due to be renewed.",
            'expired' => "The sign-in for {$account->email} has expired.",
            'missing' => 'No Google account is connected.',
        });

        if ($status === 'ok') {
            Cache::forget(self::REMINDED);

            return self::SUCCESS;
        }

        $last = Cache::get(self::REMINDED);

        if (! $this->option('force') && $last && Carbon::parse($last)->gt(now()->subHours(20))) {
            $this->line('Already emailed at '.$last.'; not again today.');

            return self::SUCCESS;
        }

        $recipients = $this->option('to') ?: config('sales_form.google.reauth_recipients');

        if (! $recipients) {
            $this->warn('No one to email (GOOGLE_CALENDAR_REAUTH_RECIPIENTS).');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('Would email '.implode(', ', $recipients).'.');

            return self::SUCCESS;
        }

        Mail::send(new GoogleCalendarReauth($recipients, $status, $account?->email, $account?->expired_at));

        if (! $this->option('to')) {
            Cache::put(self::REMINDED, now()->toDateTimeString(), now()->addDays(2));
        }

        $this->info('Emailed '.implode(', ', $recipients).'.');

        return self::SUCCESS;
    }
}
