@php
    $NAVY='#011B35'; $BLUE='#004EF0'; $T100='#D7E2FF'; $T300='#7EA2FF';
    $PAGE='#F3F6FF'; $BORDER='#DCE4F5'; $INK='#1B2733'; $MUTED='#5A6B80'; $RED='#B42318';
    $FONT="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    $local = fn ($at) => $at->copy()->setTimezone($zone);
    $days = fn (int $n) => $n === 0 ? 'today' : $n.' day'.($n === 1 ? '' : 's');
    $zoneLabel = $local($now)->format('T');

    $th = "font-family:{$FONT};font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:{$MUTED};padding:8px 8px;border-bottom:2px solid {$BORDER};text-align:left;";
    $td = "font-family:{$FONT};font-size:13px;line-height:19px;color:{$INK};padding:9px 8px;border-bottom:1px solid {$BORDER};vertical-align:top;";
    $h2 = "font-family:{$FONT};font-size:16px;font-weight:700;color:{$NAVY};padding-bottom:4px;";
    $sub = "font-family:{$FONT};font-size:13px;line-height:19px;color:{$MUTED};padding-bottom:12px;";
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leads to chase</title></head>
<body style="margin:0;padding:0;background-color:{{ $PAGE }};">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{{ $PAGE }};">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="760"
         style="width:760px;max-width:100%;background-color:#ffffff;border:1px solid {{ $BORDER }};border-radius:12px;overflow:hidden;">

    <tr><td align="center" style="padding:26px 24px 20px 24px;">
      <img src="{{ $message->embed($logoPath) }}" alt="12NexusBPO" width="150"
           style="display:block;width:150px;max-width:55%;height:auto;border:0;">
    </td></tr>

    <tr><td bgcolor="{{ $NAVY }}" style="padding:24px 32px;background-color:{{ $NAVY }};">
      <div style="font-family:{{ $FONT }};font-size:12px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:{{ $T300 }};padding-bottom:8px;">
        {{ $agentName ? 'Your daily pipeline check' : 'Daily pipeline check' }}
      </div>
      <div style="font-family:{{ $FONT }};font-size:23px;line-height:31px;font-weight:700;color:#ffffff;">
        {{ $overdue->count() }} overdue follow-up{{ $overdue->count() === 1 ? '' : 's' }} &middot; {{ $rotten->count() }} rotten lead{{ $rotten->count() === 1 ? '' : 's' }}
      </div>
      <div style="font-family:{{ $FONT }};font-size:14px;line-height:22px;color:{{ $T100 }};padding-top:9px;">
        @if ($agentName)
          Hi {{ strtok($agentName, ' ') }}, these are the open leads you own that need chasing, as of {{ $local($now)->format('D j M Y, g:i A T') }}.
        @else
          Open leads only (not Won, Lost or archived), as of {{ $local($now)->format('D j M Y, g:i A T') }}.
        @endif
      </div>
    </td></tr>

    @unless ($agentName || ($overdue->isEmpty() && $rotten->isEmpty()))
      @php
        $agents = $overdue->pluck('owner_name')->merge($rotten->pluck('owner_name'))
            ->map(fn ($name) => $name ?: 'Unassigned')->unique()->sort()->values();
      @endphp
      <tr><td style="padding:26px 32px 6px 32px;">
        <div style="{{ $h2 }}">By agent</div>
        <div style="{{ $sub }}">Each agent also gets their own copy of their rows below.</div>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
          <tr>
            <th style="{{ $th }}">Agent</th>
            <th style="{{ $th }}text-align:right;">Overdue follow-ups</th>
            <th style="{{ $th }}text-align:right;">Rotten leads</th>
          </tr>
          @foreach ($agents as $agent)
            <tr>
              <td style="{{ $td }}font-weight:600;">{{ $agent }}</td>
              <td style="{{ $td }}text-align:right;">{{ $overdue->filter(fn ($r) => ($r->owner_name ?: 'Unassigned') === $agent)->count() }}</td>
              <td style="{{ $td }}text-align:right;">{{ $rotten->filter(fn ($r) => ($r->owner_name ?: 'Unassigned') === $agent)->count() }}</td>
            </tr>
          @endforeach
        </table>
      </td></tr>
    @endunless

    <tr><td style="padding:26px 32px 6px 32px;">
      <div style="{{ $h2 }}">Overdue follow-ups</div>
      <div style="{{ $sub }}">Calls and meetings past their time and not marked done. Times in {{ $zoneLabel }}.</div>
      @if ($overdue->isEmpty())
        <div style="{{ $td }}border:0;color:{{ $MUTED }};">None. Every follow-up is done or still ahead.</div>
      @else
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
          <tr>
            <th style="{{ $th }}">Lead</th>
            @unless ($agentName)<th style="{{ $th }}">Agent</th>@endunless
            <th style="{{ $th }}">Stage</th>
            <th style="{{ $th }}">Follow-up</th>
            <th style="{{ $th }}">Was due</th>
            <th style="{{ $th }}text-align:right;">Overdue</th>
          </tr>
          @foreach ($overdue as $row)
            <tr>
              <td style="{{ $td }}"><a href="{{ $leadUrl($row->lead_id) }}" style="color:{{ $BLUE }};font-weight:600;text-decoration:none;">{{ $row->client_name ?: $row->lead_title }}</a></td>
              @unless ($agentName)<td style="{{ $td }}">{{ $row->owner_name ?: 'Unassigned' }}</td>@endunless
              <td style="{{ $td }}">{{ $row->stage_name }}</td>
              <td style="{{ $td }}">{{ ucfirst($row->activity_type) }}@if ($row->activity_title)<br><span style="color:{{ $MUTED }};">{{ $row->activity_title }}</span>@endif</td>
              <td style="{{ $td }}white-space:nowrap;">{{ $local($row->due)->format('D j M, g:i A') }}</td>
              <td style="{{ $td }}text-align:right;white-space:nowrap;color:{{ $RED }};font-weight:600;">{{ $days($row->days_overdue) }}</td>
            </tr>
          @endforeach
        </table>
      @endif
    </td></tr>

    <tr><td style="padding:26px 32px 6px 32px;">
      <div style="{{ $h2 }}">Rotten leads</div>
      <div style="{{ $sub }}">Leads that have been open longer than their pipeline's rotten days.</div>
      @if ($rotten->isEmpty())
        <div style="{{ $td }}border:0;color:{{ $MUTED }};">None.</div>
      @else
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
          <tr>
            <th style="{{ $th }}">Lead</th>
            @unless ($agentName)<th style="{{ $th }}">Agent</th>@endunless
            <th style="{{ $th }}">Stage</th>
            <th style="{{ $th }}">Created</th>
            <th style="{{ $th }}">Last activity</th>
            <th style="{{ $th }}text-align:right;">Rotten for</th>
          </tr>
          @foreach ($rotten as $row)
            <tr>
              <td style="{{ $td }}"><a href="{{ $leadUrl($row->lead_id) }}" style="color:{{ $BLUE }};font-weight:600;text-decoration:none;">{{ $row->client_name ?: $row->lead_title }}</a></td>
              @unless ($agentName)<td style="{{ $td }}">{{ $row->owner_name ?: 'Unassigned' }}</td>@endunless
              <td style="{{ $td }}">{{ $row->stage_name }}</td>
              <td style="{{ $td }}white-space:nowrap;">{{ $local($row->created)->format('j M Y') }}</td>
              <td style="{{ $td }}white-space:nowrap;">{{ $row->last_activity ? $local($row->last_activity)->format('j M Y') : 'Never' }}</td>
              <td style="{{ $td }}text-align:right;white-space:nowrap;color:{{ $RED }};font-weight:600;">{{ $days($row->days_rotten) }}</td>
            </tr>
          @endforeach
        </table>
      @endif
    </td></tr>

    <tr><td style="padding:22px 32px 26px 32px;font-family:{{ $FONT }};font-size:12px;line-height:18px;color:{{ $MUTED }};">
      @if ($agentName)
        Sent every morning at 8:00 AM Pacific by the CRM. To clear a follow-up, open the lead and mark the call or meeting done, record the meeting as held or a no-show, or reschedule it. A lead leaves the rotten list once it is Won, Lost or marked invalid.
      @else
        Sent every morning at 8:00 AM Pacific by the CRM. A follow-up drops off this list once it is marked done or rescheduled; a lead drops off once it is Won, Lost or archived.
      @endif
    </td></tr>

  </table>
</td></tr>
</table>
</body></html>
