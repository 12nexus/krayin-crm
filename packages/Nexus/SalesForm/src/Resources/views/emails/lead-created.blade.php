@php
    $NAVY='#011B35'; $BLUE='#004EF0'; $T50='#EAF0FF'; $T100='#D7E2FF'; $T300='#7EA2FF';
    $PAGE='#F3F6FF'; $BORDER='#DCE4F5'; $INK='#1B2733'; $MUTED='#5A6B80';
    $FONT="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    $rows = array_filter([
        'Sales executive' => $d['owner_name'],
        'Client'          => $d['client_name'],
        'Email'           => $d['client_email'],
        'Phone'           => $d['client_phone'],
        'Brokerage'       => $d['brokerage'],
        'Location'        => trim(implode(', ', array_filter([$d['city'], $d['state'], $d['country']])), ', '),
        'Experience'      => $d['experience'],
        'Using assistant' => $d['assistant'] ? $d['assistant'].($d['assistant_type'] && $d['assistant_type'] !== 'None' ? ' ('.$d['assistant_type'].')' : '') : '',
        'Willingness'     => $d['willingness'],
        'Stage'           => $d['stage'],
        'Source'          => $d['source'],
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $kind === 'meeting' ? 'Meeting scheduled' : 'New lead' }}</title></head>
<body style="margin:0;padding:0;background-color:{{ $PAGE }};">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    @if ($kind === 'meeting')
        {{ $d['client_name'] ?: 'A lead' }} has a meeting booked{{ $d['has_meeting'] ? ' for '.$d['meeting_display'].' '.$d['timezone_label'] : '' }} ({{ $d['owner_name'] }}).
    @else
        {{ $d['client_name'] ?: 'A new lead' }} was added by {{ $d['owner_name'] }}{{ $d['has_meeting'] ? ', meeting '.$d['meeting_display'].' '.$d['timezone_label'] : '' }}.
    @endif
</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{{ $PAGE }};">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
         style="width:600px;max-width:100%;background-color:#ffffff;border:1px solid {{ $BORDER }};border-radius:12px;overflow:hidden;">

    <tr><td align="center" style="padding:26px 24px 20px 24px;">
      <img src="{{ $message->embed($logoPath) }}" alt="12NexusBPO" width="150"
           style="display:block;width:150px;max-width:55%;height:auto;border:0;">
    </td></tr>

    <tr><td bgcolor="{{ $NAVY }}" style="padding:26px 32px;background-color:{{ $NAVY }};">
      <div style="font-family:{{ $FONT }};font-size:12px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:{{ $T300 }};padding-bottom:8px;">
        {{ $kind === 'meeting' ? 'Meeting scheduled' : 'New lead created' }}
      </div>
      <div style="font-family:{{ $FONT }};font-size:23px;line-height:31px;font-weight:700;color:#ffffff;">
        {{ $d['client_name'] ?: $d['title'] }}
      </div>
      <div style="font-family:{{ $FONT }};font-size:14px;line-height:22px;color:{{ $T100 }};padding-top:9px;">
        @if ($kind === 'meeting')
          Booked by {{ $bookedBy ?: $d['owner_name'] }} on {{ now()->format('Y-m-d H:i') }} UTC. Lead owner: {{ $d['owner_name'] }}.
        @else
          Logged by {{ $d['owner_name'] }}{{ $d['created_at'] ? ' on '.$d['created_at'].' UTC' : '' }}.
        @endif
      </div>
    </td></tr>

    @if ($d['has_meeting'])
      <tr><td style="padding:24px 32px 0 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="background-color:{{ $T50 }};border:1px solid {{ $T100 }};border-radius:10px;">
          <tr><td style="padding:18px 22px;font-family:{{ $FONT }};">
            <div style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $NAVY }};padding-bottom:8px;">
              {{ $kind === 'meeting' ? 'Meeting' : 'Discovery meeting' }}
            </div>
            <div style="font-size:17px;font-weight:700;color:{{ $INK }};line-height:25px;">
              {{ $d['meeting_display'] }}
            </div>
            <div style="font-size:13px;color:{{ $MUTED }};padding-top:4px;">
              {{ $d['timezone_label'] }}, the client's local time
            </div>
          </td></tr>
        </table>
      </td></tr>

      <tr><td align="center" style="padding:20px 32px 4px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center" bgcolor="{{ $BLUE }}" style="border-radius:6px;">
            <a href="{{ $calendarUrl }}" style="display:inline-block;padding:14px 30px;font-family:{{ $FONT }};font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">
              Add to Google Calendar
            </a>
          </td></tr></table>
        <div style="font-family:{{ $FONT }};font-size:12px;line-height:19px;color:{{ $MUTED }};padding-top:11px;">
          Opens Google Calendar with the time, title and guests already filled in.<br>
          Pick the calendar you want it in and save.
        </div>
      </td></tr>
    @else
      <tr><td style="padding:22px 32px 0 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="background-color:#FFF8E6;border-left:4px solid #E9A100;border-radius:0 8px 8px 0;">
          <tr><td style="padding:14px 18px;font-family:{{ $FONT }};font-size:14px;line-height:21px;color:#6B4E00;">
            No meeting date was recorded on this lead, so there is nothing to add to a calendar yet.
          </td></tr>
        </table>
      </td></tr>
    @endif

    <tr><td style="padding:26px 32px 6px 32px;font-family:{{ $FONT }};">
      <div style="font-size:16px;font-weight:700;color:{{ $NAVY }};padding-bottom:12px;">Lead details</div>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        @foreach ($rows as $label => $value)
          <tr>
            <td valign="top" width="132" style="padding:6px 12px 6px 0;font-size:13px;color:{{ $MUTED }};border-bottom:1px solid {{ $BORDER }};">{{ $label }}</td>
            <td valign="top" style="padding:6px 0;font-size:14px;color:{{ $INK }};font-weight:600;border-bottom:1px solid {{ $BORDER }};">{{ $value }}</td>
          </tr>
        @endforeach
      </table>
    </td></tr>

    @if ($d['notes'])
      <tr><td style="padding:18px 32px 0 32px;font-family:{{ $FONT }};">
        <div style="font-size:13px;font-weight:700;color:{{ $NAVY }};padding-bottom:5px;">Notes from the call</div>
        <div style="font-size:14px;line-height:22px;color:{{ $MUTED }};">{{ $d['notes'] }}</div>
      </td></tr>
    @endif

    <tr><td align="center" style="padding:24px 32px 30px 32px;">
      <a href="{{ $d['url'] }}" style="font-family:{{ $FONT }};font-size:14px;font-weight:600;color:{{ $BLUE }};text-decoration:none;">
        Open this lead in the CRM
      </a>
    </td></tr>

    <tr><td bgcolor="{{ $NAVY }}" align="center" style="padding:18px 30px;background-color:{{ $NAVY }};font-family:{{ $FONT }};">
      <div style="font-size:13px;font-weight:600;color:#ffffff;">12NexusBPO</div>
      <div style="font-size:12px;line-height:19px;color:{{ $T300 }};padding-top:4px;">
        Automatic notification from the CRM. No need to reply.
      </div>
    </td></tr>

  </table>
</td></tr></table>
</body></html>
