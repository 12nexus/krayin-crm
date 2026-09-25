<?php

namespace Nexus\SalesForm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Nexus\SalesForm\Services\GoogleCalendar;
use Nexus\SalesForm\Services\MeetingCalendarSync;

/**
 * Settings → Google Calendar: connect the account the CRM books meetings as,
 * see whether it is working, and link events made before the sync existed.
 * Administrators only.
 */
class GoogleCalendarController extends Controller
{
    public function __construct(
        protected GoogleCalendar $google,
        protected MeetingCalendarSync $sync,
    ) {}

    public function index(): View
    {
        $this->authorizeAdmin();

        $account = $this->google->account();

        $accessRole = null;
        $accessError = null;

        if ($this->google->connected()) {
            try {
                $accessRole = $this->google->accessRole();
            } catch (\Throwable $e) {
                $accessError = $e->getMessage();
                $account = $this->google->account();
            }
        }

        return view('sales_form::google-calendar.index', [
            'configured'  => $this->google->configured(),
            'calendarId'  => $this->google->calendarId(),
            'account'     => $account,
            'connected'   => $this->google->connected(),
            'accessRole'  => $accessRole,
            'accessError' => $accessError,
            'redirectUri' => $this->google->redirectUri(),
            'recent'      => DB::table('nexus_calendar_events as e')
                ->leftJoin('leads', 'leads.id', '=', 'e.lead_id')
                ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
                ->orderByDesc('e.updated_at')
                ->limit(12)
                ->get(['e.*', 'persons.name as client']),
        ]);
    }

    public function connect(): RedirectResponse
    {
        $this->authorizeAdmin();

        abort_unless($this->google->configured(), 404);

        $state = Str::random(40);

        session(['nexus_google_oauth_state' => $state]);

        return redirect()->away($this->google->authorizationUrl($state, auth()->guard('user')->user()?->email));
    }

    public function callback(): RedirectResponse
    {
        $this->authorizeAdmin();

        $back = redirect()->route('admin.settings.google_calendar.index');

        $expected = session()->pull('nexus_google_oauth_state');

        if (! $expected || ! hash_equals($expected, (string) request('state'))) {
            return $back->with('error', trans('sales_form::app.google.state-mismatch'));
        }

        if (request('error')) {
            return $back->with('error', trans('sales_form::app.google.denied'));
        }

        try {
            $account = $this->google->connect((string) request('code'), auth()->guard('user')->id());

            $role = $this->google->accessRole();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar connect failed', ['message' => $e->getMessage()]);

            return $back->with('error', trans('sales_form::app.google.connect-failed', ['message' => Str::limit($e->getMessage(), 200)]));
        }

        if (! in_array($role, ['owner', 'writer'], true)) {
            return $back->with('warning', trans('sales_form::app.google.no-write', ['email' => $account->email, 'role' => $role ?: 'none']));
        }

        return $back->with('success', trans('sales_form::app.google.connected', ['email' => $account->email]));
    }

    public function disconnect(): RedirectResponse
    {
        $this->authorizeAdmin();

        $this->google->disconnect();

        return redirect()->route('admin.settings.google_calendar.index')
            ->with('success', trans('sales_form::app.google.disconnected'));
    }

    public function linkExisting(): RedirectResponse
    {
        $this->authorizeAdmin();

        abort_unless($this->google->connected(), 404);

        try {
            $result = $this->sync->linkExisting();
        } catch (\Throwable $e) {
            return back()->with('error', Str::limit($e->getMessage(), 200));
        }

        return redirect()->route('admin.settings.google_calendar.index')->with('success', trans('sales_form::app.google.linked', [
            'count'   => $result['linked'],
            'missing' => $result['missing'] ? ' '.trans('sales_form::app.google.linked-missing', ['names' => implode(', ', $result['missing'])]) : '',
        ]));
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(bouncer()->hasPermission('settings.user.users'), 401);
    }
}
