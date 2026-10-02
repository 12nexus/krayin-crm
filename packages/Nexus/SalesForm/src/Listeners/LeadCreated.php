<?php

namespace Nexus\SalesForm\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nexus\SalesForm\Mail\LeadCreatedNotification;
use Nexus\SalesForm\Services\CalendarFeed;
use Nexus\SalesForm\Services\CalendarLink;
use Nexus\SalesForm\Services\ClientInvite;
use Nexus\SalesForm\Services\LeadDigest;

class LeadCreated
{
    public function __construct(
        protected LeadDigest $digest,
        protected CalendarLink $calendar,
    ) {}

    /**
     * When the time it had before a reschedule (UTC), so the email can open that
     * event in the shared calendar to be moved instead of adding a second one.
     */
    protected ?string $previousStartUtc = null;

    /**
     * A meeting moved to a new time: the same notification, pointing at the
     * calendar event that already exists for it.
     */
    public function rescheduled($lead, ?string $previousStartUtc): void
    {
        $this->previousStartUtc = $previousStartUtc;

        try {
            $this->handle($lead, 'rescheduled');
        } finally {
            $this->previousStartUtc = null;
        }
    }

    /**
     * Notify every administrator that a lead has landed, with a one-click Google
     * Calendar link for the discovery meeting.
     *
     * Never throws: a notification problem must not surface as a failed lead
     * submission for the rep who just filled the form in.
     *
     * @param  string  $kind  created | meeting | rescheduled | follow-up (see LeadCreatedNotification::headline)
     */
    public function handle($lead, $kind = 'created'): void
    {
        // Krayin's event dispatcher may pass extra payload; only the known kinds
        // are meaningful.
        $kind = in_array($kind, ['meeting', 'rescheduled', 'follow-up'], true) ? $kind : 'created';

        if (! config('sales_form.notify.enabled')) {
            return;
        }

        try {
            $digest = $this->digest->build($lead);
            $recipients = $this->administrators();

            $existingEvent = $kind === 'rescheduled' && $digest['has_meeting']
                ? $this->existingEventUrl($digest)
                : null;

            foreach ($recipients as $admin) {
                $calendarUrl = $existingEvent ?? ($digest['has_meeting']
                    ? $this->calendarUrl($digest, $admin['email'])
                    : null);

                $mail = new LeadCreatedNotification(
                    $admin['email'],
                    $admin['name'],
                    $digest,
                    $calendarUrl,
                    $kind,
                    auth()->guard('user')->user()?->name,
                );

                $mail->movesEvent = $existingEvent !== null;

                Mail::queue($mail);
            }

            Log::info('Lead notification queued', [
                'lead_id' => $digest['id'],
                'recipients' => array_column($recipients, 'email'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Lead notification failed', [
                'lead_id' => $lead->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Everyone holding a full-access role, with the configured fallback if that
     * lookup comes back empty or blows up — the brief is that someone is always
     * told, even when the role table cannot be read.
     */
    protected function administrators(): array
    {
        $fallback = [[
            'email' => config('sales_form.notify.fallback_email'),
            'name'  => config('sales_form.notify.fallback_name'),
        ]];

        try {
            $admins = DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.permission_type', 'all')
                ->where('users.status', 1)
                ->whereNotNull('users.email')
                ->get(['users.name', 'users.email'])
                ->map(fn ($user) => ['email' => $user->email, 'name' => $user->name])
                ->filter(fn ($user) => filter_var($user['email'], FILTER_VALIDATE_EMAIL) !== false)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Could not read the administrator list; using the fallback address', [
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }

        return $admins ?: $fallback;
    }

    /**
     * The edit page of the event already in the shared sales calendar at the
     * meeting's old time, or null when it cannot be found (not added yet, the
     * feed is down), in which case the email falls back to a new-event link.
     */
    protected function existingEventUrl(array $digest): ?string
    {
        if (! $this->previousStartUtc) {
            return null;
        }

        try {
            return app(CalendarFeed::class)->editUrl(
                Carbon::parse($this->previousStartUtc, 'UTC'),
                $digest['client_name'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning('Could not look up the rescheduled meeting in the sales calendar', [
                'lead_id' => $digest['id'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function calendarUrl(array $digest, string $adminEmail): string
    {
        $title = sprintf(
            '%s | Virtual Assistant Discovery Call',
            $digest['client_name'] ?: 'Lead'
        );

        // The client is a guest on this event and reads the description.
        $details = app(ClientInvite::class)->description(
            $digest['client_name'],
            $digest['owner_name'],
            $digest['owner_email'],
        );

        return $this->calendar->build(
            $title,
            Carbon::parse($digest['meeting_local']),
            $digest['timezone'],
            (int) config('sales_form.notify.meeting_minutes'),
            [$digest['client_email'], $digest['owner_email'], $adminEmail],
            $details,
            config('sales_form.notify.meeting_location'),
        );
    }
}
