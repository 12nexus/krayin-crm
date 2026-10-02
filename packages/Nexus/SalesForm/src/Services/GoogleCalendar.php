<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Write access to the shared sales calendar, through the Google account an
 * administrator connects under Settings → Google Calendar (OAuth, offline
 * access). Used to move a meeting's existing event when it is rescheduled, so
 * the guests get Google's update rather than a second invite.
 *
 * New meetings are not written here: they still go to the administrators by
 * email, with a link that adds them to the calendar.
 */
class GoogleCalendar
{
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    const API = 'https://www.googleapis.com/calendar/v3';

    const SCOPES = 'openid email https://www.googleapis.com/auth/calendar.events';

    /**
     * The OAuth client is set up in .env (GOOGLE_CALENDAR_CLIENT_ID/SECRET).
     */
    public function configured(): bool
    {
        return config('sales_form.google.client_id')
            && config('sales_form.google.client_secret')
            && $this->calendarId();
    }

    public function calendarId(): ?string
    {
        return config('sales_form.notify.calendar_id') ?: null;
    }

    public function account(): ?object
    {
        return DB::table('nexus_google_calendar_accounts')->latest('id')->first();
    }

    public function connected(): bool
    {
        return $this->configured() && $this->account() !== null;
    }

    public function redirectUri(): string
    {
        return route('admin.settings.google_calendar.callback');
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id'     => config('sales_form.google.client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Swap the code Google sent back for tokens, check the account can write to
     * the sales calendar, and keep it as the connected account.
     */
    public function connect(string $code, ?int $userId): string
    {
        $tokens = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('sales_form.google.client_id'),
            'client_secret' => config('sales_form.google.client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ])->throw()->json();

        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Google did not return a refresh token. Remove the CRM from the account\'s third-party access and connect again.');
        }

        $email = Http::withToken($tokens['access_token'])->timeout(15)
            ->get(self::USERINFO_URL)->throw()->json('email');

        $this->assertCanWrite($tokens['access_token'], $email);

        DB::transaction(function () use ($tokens, $email, $userId) {
            DB::table('nexus_google_calendar_accounts')->delete();

            DB::table('nexus_google_calendar_accounts')->insert([
                'email'                   => $email,
                'refresh_token'           => Crypt::encryptString($tokens['refresh_token']),
                'access_token'            => Crypt::encryptString($tokens['access_token']),
                'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600) - 60),
                'connected_by'            => $userId,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        });

        return $email;
    }

    /**
     * Writing needs at least "Make changes to events" on the sales calendar.
     * Checked through the calendar's event list, whose accessRole is the
     * account's own; the calendar list would need a wider scope than
     * calendar.events, and only holds calendars the account has added.
     */
    protected function assertCanWrite(string $accessToken, ?string $email): void
    {
        $response = Http::withToken($accessToken)->timeout(15)->acceptJson()
            ->get(self::API.'/calendars/'.rawurlencode($this->calendarId()).'/events', ['maxResults' => 1]);

        if (! $response->successful()) {
            $reason = $response->json('error.errors.0.reason') ?? $response->json('error.status');

            Log::warning('Google Calendar access check failed', [
                'email'   => $email,
                'status'  => $response->status(),
                'reason'  => $reason,
                'message' => $response->json('error.message'),
            ]);

            if (in_array($reason, ['accessNotConfigured', 'SERVICE_DISABLED'], true)) {
                throw new RuntimeException('The Google Calendar API is not enabled for this OAuth client\'s Cloud project. Enable it under APIs & Services, then connect again.');
            }

            if ($response->status() === 404) {
                throw new RuntimeException("{$email} cannot see the sales calendar. Share it with this account (\"Make changes to events\"), then connect again.");
            }

            throw new RuntimeException('Google refused access to the sales calendar ('.($response->json('error.message') ?: $response->status()).').');
        }

        $role = $response->json('accessRole');

        if (! in_array($role, ['writer', 'owner'], true)) {
            Log::warning('Google Calendar account cannot write', ['email' => $email, 'access_role' => $role]);

            throw new RuntimeException("{$email} can only see the sales calendar ({$role}), not make changes to events. Give it \"Make changes to events\", or connect an account that has it.");
        }
    }

    public function disconnect(): void
    {
        if ($account = $this->account()) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
                    'token' => Crypt::decryptString($account->refresh_token),
                ]);
            } catch (\Throwable) {
                // Forgetting the token here is what matters.
            }
        }

        DB::table('nexus_google_calendar_accounts')->delete();
    }

    /**
     * Find the meeting's event and give it the new time. Returns the event's
     * Google Calendar link, or null when there is nothing to move (not
     * connected, or no event for the meeting in the sales calendar).
     *
     * @param  int  $fromActivityId  the meeting as it was (its event may be known)
     * @param  int  $toActivityId    the meeting now (the same one, unless a no-show was rebooked)
     */
    public function moveMeeting(
        int $fromActivityId,
        int $toActivityId,
        Carbon $previousStartUtc,
        Carbon $startUtc,
        Carbon $endUtc,
        string $timezone,
        ?string $clientName
    ): ?string {
        if (! $this->connected()) {
            return null;
        }

        $eventId = DB::table('nexus_meeting_calendar_events')
            ->whereIn('activity_id', [$fromActivityId, $toActivityId])
            ->orderByRaw('activity_id = ? desc', [$toActivityId])
            ->value('google_event_id')
            ?? $this->findEvent($previousStartUtc, $clientName);

        if (! $eventId) {
            return null;
        }

        $event = $this->request('patch', '/calendars/'.rawurlencode($this->calendarId()).'/events/'.rawurlencode($eventId).'?sendUpdates=all', [
            'start' => ['dateTime' => $startUtc->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
            'end'   => ['dateTime' => $endUtc->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
        ]);

        DB::table('nexus_meeting_calendar_events')->updateOrInsert(
            ['activity_id' => $toActivityId],
            ['google_event_id' => $eventId, 'created_at' => now(), 'updated_at' => now()]
        );

        return $event['htmlLink'] ?? null;
    }

    /**
     * The event at $startUtc whose title names the client. Without a name, only
     * an event alone at that time counts: another client's meeting is not this one.
     */
    public function findEvent(Carbon $startUtc, ?string $clientName): ?string
    {
        $items = $this->request('get', '/calendars/'.rawurlencode($this->calendarId()).'/events?'.http_build_query([
            'timeMin'      => $startUtc->copy()->utc()->subMinute()->format('Y-m-d\TH:i:s\Z'),
            'timeMax'      => $startUtc->copy()->utc()->addMinute()->format('Y-m-d\TH:i:s\Z'),
            'singleEvents' => 'true',
            'maxResults'   => 50,
        ]))['items'] ?? [];

        $name = mb_strtolower(trim((string) $clientName));
        $matches = [];

        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'cancelled' || empty($item['start']['dateTime'])) {
                continue;
            }

            if (! Carbon::parse($item['start']['dateTime'])->utc()->equalTo($startUtc->copy()->utc()->startOfMinute())) {
                continue;
            }

            $matches[] = [
                'id'    => $item['id'],
                'named' => $name !== '' && str_contains(mb_strtolower($item['summary'] ?? ''), $name),
            ];
        }

        $named = array_values(array_filter($matches, fn ($match) => $match['named']));

        return ($named[0] ?? (count($matches) === 1 && $name === '' ? $matches[0] : null))['id'] ?? null;
    }

    protected function request(string $method, string $path, array $body = []): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->acceptJson()
            ->send(strtoupper($method), self::API.$path, $body ? ['json' => $body] : []);

        return $response->throw()->json() ?? [];
    }

    protected function accessToken(): string
    {
        $account = $this->account() ?? throw new RuntimeException('Google Calendar is not connected.');

        if ($account->access_token && $account->access_token_expires_at && now()->lt($account->access_token_expires_at)) {
            return Crypt::decryptString($account->access_token);
        }

        $tokens = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id'     => config('sales_form.google.client_id'),
            'client_secret' => config('sales_form.google.client_secret'),
            'refresh_token' => Crypt::decryptString($account->refresh_token),
            'grant_type'    => 'refresh_token',
        ])->throw()->json();

        DB::table('nexus_google_calendar_accounts')->where('id', $account->id)->update([
            'access_token'            => Crypt::encryptString($tokens['access_token']),
            'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600) - 60),
            'updated_at'              => now(),
        ]);

        return $tokens['access_token'];
    }
}
