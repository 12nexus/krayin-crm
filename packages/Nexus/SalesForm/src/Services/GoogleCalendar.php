<?php

namespace Nexus\SalesForm\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar access as the one connected account (Shehzer's).
 *
 * OAuth "web server" flow: an administrator connects once from Settings →
 * Google Calendar; the refresh token is stored encrypted and exchanged for
 * short-lived access tokens as needed. Nothing here ever logs a token or the
 * client secret.
 */
class GoogleCalendar
{
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    const API = 'https://www.googleapis.com/calendar/v3';

    const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/calendar.events'];

    /**
     * The OAuth client (from the GCP project) is configured in .env.
     */
    public function configured(): bool
    {
        return (bool) (config('sales_form.google.client_id') && config('sales_form.google.client_secret'));
    }

    public function calendarId(): ?string
    {
        return config('sales_form.notify.calendar_id') ?: null;
    }

    public function account(): ?object
    {
        return DB::table('nexus_google_accounts')->orderByDesc('id')->first();
    }

    /**
     * Connected, and the token still works as far as we know.
     */
    public function connected(): bool
    {
        $account = $this->account();

        return $this->configured() && $this->calendarId() && $account && $account->status === 'connected';
    }

    public function redirectUri(): string
    {
        return route('admin.settings.google_calendar.callback');
    }

    public function authorizationUrl(string $state, ?string $loginHint = null): string
    {
        return self::AUTH_URL.'?'.http_build_query(array_filter([
            'client_id'              => config('sales_form.google.client_id'),
            'redirect_uri'           => $this->redirectUri(),
            'response_type'          => 'code',
            'scope'                  => implode(' ', self::SCOPES),
            // A refresh token is only issued with offline access, and only on
            // a fresh consent, so both are forced.
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
            'login_hint'             => $loginHint,
        ]));
    }

    /**
     * Swap the authorisation code for tokens and store the connection.
     *
     * @return object the stored account row
     */
    public function connect(string $code, ?int $userId): object
    {
        $tokens = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('sales_form.google.client_id'),
            'client_secret' => config('sales_form.google.client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ])->throw()->json();

        if (empty($tokens['refresh_token'])) {
            throw new \RuntimeException('Google did not return a refresh token. Remove the app from the account\'s third-party access and connect again.');
        }

        if (! str_contains($tokens['scope'] ?? '', 'calendar.events')) {
            throw new \RuntimeException('Calendar access was not granted. Connect again and tick the calendar permission.');
        }

        $email = $this->emailFromIdToken($tokens['id_token'] ?? '') ?? 'unknown';

        // One connection at a time: replacing it revokes nothing else.
        DB::table('nexus_google_accounts')->delete();

        $id = DB::table('nexus_google_accounts')->insertGetId([
            'email'                   => $email,
            'refresh_token'           => Crypt::encryptString($tokens['refresh_token']),
            'access_token'            => Crypt::encryptString($tokens['access_token']),
            'access_token_expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 60)),
            'scopes'                  => $tokens['scope'] ?? null,
            'status'                  => 'connected',
            'connected_by'            => $userId,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        return DB::table('nexus_google_accounts')->find($id);
    }

    public function disconnect(): void
    {
        $account = $this->account();

        if ($account) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
                    'token' => Crypt::decryptString($account->refresh_token),
                ]);
            } catch (\Throwable) {
                // Revocation is best effort; the stored token is dropped anyway.
            }
        }

        DB::table('nexus_google_accounts')->delete();
    }

    /**
     * The access role the connected account has on the sales calendar
     * (owner, writer, reader, freeBusyReader), read from an events listing.
     */
    public function accessRole(): ?string
    {
        return $this->request('get', '/calendars/'.rawurlencode($this->calendarId()).'/events', [
            'maxResults' => 1,
        ])->json('accessRole');
    }

    public function insertEvent(array $event): array
    {
        return $this->request('post', $this->eventsPath().'?'.http_build_query([
            'sendUpdates'           => 'all',
            'conferenceDataVersion' => 1,
        ]), $event)->json();
    }

    public function patchEvent(string $eventId, array $event): array
    {
        return $this->request('patch', $this->eventsPath().'/'.rawurlencode($eventId).'?'.http_build_query([
            'sendUpdates'           => 'all',
            'conferenceDataVersion' => 1,
        ]), $event)->json();
    }

    public function deleteEvent(string $eventId): void
    {
        $response = $this->request('delete', $this->eventsPath().'/'.rawurlencode($eventId).'?sendUpdates=all', [], false);

        // Already gone is as good as deleted.
        if ($response->failed() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }
    }

    public function listEvents(array $query): array
    {
        return $this->request('get', $this->eventsPath(), $query + ['singleEvents' => 'true'])->json('items') ?? [];
    }

    protected function eventsPath(): string
    {
        return '/calendars/'.rawurlencode($this->calendarId()).'/events';
    }

    /**
     * An authorised Calendar API call. A 401 gets one retry with a fresh token.
     */
    protected function request(string $method, string $path, array $data = [], bool $throw = true): Response
    {
        $send = function () use ($method, $path, $data) {
            $client = Http::withToken($this->accessToken())->acceptJson()->timeout(15);

            return $method === 'get'
                ? $client->get(self::API.$path, $data)
                : $client->{$method}(self::API.$path, $data);
        };

        $response = $send();

        if ($response->status() === 401) {
            $this->expireAccessToken();

            $response = $send();
        }

        return $throw ? $response->throw() : $response;
    }

    protected function accessToken(): string
    {
        $account = $this->account();

        if (! $account || $account->status !== 'connected') {
            throw new \RuntimeException('Google Calendar is not connected.');
        }

        if ($account->access_token && $account->access_token_expires_at && now()->lt($account->access_token_expires_at)) {
            return Crypt::decryptString($account->access_token);
        }

        try {
            $tokens = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'client_id'     => config('sales_form.google.client_id'),
                'client_secret' => config('sales_form.google.client_secret'),
                'refresh_token' => Crypt::decryptString($account->refresh_token),
                'grant_type'    => 'refresh_token',
            ])->throw()->json();
        } catch (RequestException $e) {
            // invalid_grant: access was revoked, the password changed, or the
            // consent screen is still in Testing mode (7-day tokens). An admin
            // has to connect again; everything else keeps working meanwhile.
            if ($e->response?->json('error') === 'invalid_grant') {
                DB::table('nexus_google_accounts')->where('id', $account->id)->update([
                    'status'     => 'reconnect',
                    'last_error' => 'Google no longer accepts the saved access. Connect the calendar again.',
                    'updated_at' => now(),
                ]);
            }

            throw $e;
        }

        DB::table('nexus_google_accounts')->where('id', $account->id)->update([
            'access_token'            => Crypt::encryptString($tokens['access_token']),
            'access_token_expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 60)),
            'last_error'              => null,
            'updated_at'              => now(),
        ]);

        return $tokens['access_token'];
    }

    protected function expireAccessToken(): void
    {
        DB::table('nexus_google_accounts')->update(['access_token_expires_at' => now()->subMinute()]);
    }

    /**
     * The account's email from the ID token Google just handed us over TLS in
     * the token response; its signature does not need checking in that case.
     */
    protected function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return $payload['email'] ?? null;
    }
}
