<?php

namespace Nexus\SalesForm\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nexus\SalesForm\Mail\LeadCreatedNotification;
use Nexus\SalesForm\Services\CalendarLink;
use Nexus\SalesForm\Services\LeadDigest;

class LeadCreated
{
    public function __construct(
        protected LeadDigest $digest,
        protected CalendarLink $calendar,
    ) {}

    /**
     * Notify every administrator that a lead has landed, with a one-click Google
     * Calendar link for the discovery meeting.
     *
     * Never throws: a notification problem must not surface as a failed lead
     * submission for the rep who just filled the form in.
     *
     * @param  string  $kind  created | meeting (a meeting booked on an existing lead)
     */
    public function handle($lead, $kind = 'created'): void
    {
        // Krayin's event dispatcher may pass extra payload; only the two known
        // kinds are meaningful.
        $kind = $kind === 'meeting' ? 'meeting' : 'created';

        if (! config('sales_form.notify.enabled')) {
            return;
        }

        try {
            $digest = $this->digest->build($lead);
            $recipients = $this->administrators();

            foreach ($recipients as $admin) {
                $calendarUrl = $digest['has_meeting']
                    ? $this->calendarUrl($digest, $admin['email'])
                    : null;

                Mail::queue(new LeadCreatedNotification(
                    $admin['email'],
                    $admin['name'],
                    $digest,
                    $calendarUrl,
                    $kind,
                    auth()->guard('user')->user()?->name,
                ));
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

    protected function calendarUrl(array $digest, string $adminEmail): string
    {
        $title = sprintf(
            '%s | Virtual Assistant Discovery Call',
            $digest['client_name'] ?: 'Lead'
        );

        $details = implode("\n", array_filter([
            'Discovery call booked by '.$digest['owner_name'].'.',
            $digest['brokerage'] ? 'Brokerage: '.$digest['brokerage'] : null,
            $digest['client_phone'] ? 'Phone: '.$digest['client_phone'] : null,
            $digest['willingness'] ? 'Willingness to hire a VA: '.$digest['willingness'] : null,
            $digest['notes'] ? "\nNotes from the call: ".$digest['notes'] : null,
            "\nLead in the CRM: ".$digest['url'],
        ]));

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
