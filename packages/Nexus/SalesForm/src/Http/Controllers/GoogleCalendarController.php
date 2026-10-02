<?php

namespace Nexus\SalesForm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nexus\SalesForm\Services\GoogleCalendar;

/**
 * Settings → Google Calendar: connect the Google account the CRM uses to move
 * rescheduled meetings in the sales calendar.
 */
class GoogleCalendarController extends Controller
{
    const STATE = 'nexus.google_calendar.state';

    public function __construct(protected GoogleCalendar $calendar) {}

    public function index()
    {
        return view('sales_form::settings.google-calendar', [
            'configured'  => $this->calendar->configured(),
            'account'     => $this->calendar->account(),
            'calendarId'  => $this->calendar->calendarId(),
            'redirectUri' => $this->calendar->redirectUri(),
        ]);
    }

    public function connect(): RedirectResponse
    {
        if (! $this->calendar->configured()) {
            return back()->with('error', trans('sales_form::app.google-calendar.not-configured'));
        }

        $state = Str::random(40);

        session()->put(self::STATE, $state);

        return redirect()->away($this->calendar->authUrl($state));
    }

    public function callback(): RedirectResponse
    {
        $expected = session()->pull(self::STATE);

        $back = redirect()->route('admin.settings.google_calendar.index');

        if (request('error')) {
            return $back->with('error', trans('sales_form::app.google-calendar.denied'));
        }

        if (! $expected || ! hash_equals($expected, (string) request('state')) || ! request('code')) {
            return $back->with('error', trans('sales_form::app.google-calendar.failed'));
        }

        try {
            $email = $this->calendar->connect((string) request('code'), auth()->guard('user')->id());
        } catch (\Throwable $e) {
            Log::warning('Google Calendar connection failed', ['message' => $e->getMessage()]);

            return $back->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : trans('sales_form::app.google-calendar.failed'));
        }

        return $back->with('success', trans('sales_form::app.google-calendar.connected', ['email' => $email]));
    }

    public function disconnect(): RedirectResponse
    {
        $this->calendar->disconnect();

        return redirect()
            ->route('admin.settings.google_calendar.index')
            ->with('success', trans('sales_form::app.google-calendar.disconnected'));
    }
}
